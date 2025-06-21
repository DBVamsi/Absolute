<?php
	class Item
	{
		private $pdo;
    private $pokemonService;
    private $userService;

		/**
		 * Construct and initialize the class.
		 */
		public function __construct(PDO $pdo, PokemonService $pokemonService, User $userService)
		{
			$this->pdo = $pdo;
      $this->pokemonService = $pokemonService;
      $this->userService = $userService;
		}

		/**
		 * Fetch the data of any item via the `item_dex` table.
		 */
		public function FetchItemData($Item_ID)
		{
			try
			{
				$Fetch_Item = $this->pdo->prepare("SELECT * FROM `item_dex` WHERE `Item_ID` = ?");
				$Fetch_Item->execute([$Item_ID]);
				$Fetch_Item->setFetchMode(PDO::FETCH_ASSOC);
				$Item = $Fetch_Item->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return false;
			}

			if ( !$Item ) return false;

			return [
				"ID" => $Item['Item_ID'],
				"Name" => $Item['Item_Name'],
				"Category" => $Item['Item_Type'],
				"Description" => $Item['Item_Description'],
				"Icon" => DOMAIN_SPRITES . "/Items/{$Item['Item_Name']}.png"
			];
		}

		/**
		 * Fetch the item data of the item that a Pokemon is holding.
		 */
		public function FetchOwnedItem($Owner_ID, $Item_ID = null, $Limit = 1)
		{
			if ( !isset($Owner_ID) || !$Owner_ID )
				return false;

      // Sanitize $Limit parameter for SQL injection mitigation.
      // PDO does not typically support binding LIMIT values directly.
      $Limit = (int) $Limit;
      if ($Limit <= 0) {
        $Limit = 1; // Default to 1 if invalid or non-positive limit is provided
      }

			try
			{
				if ( $Item_ID == null )
				{
					$Fetch_Item = $this->pdo->prepare("SELECT * FROM `items` WHERE `Owner_Current` = ? LIMIT {$Limit}");
					$Fetch_Item->execute([$Owner_ID]);
				}
				else
				{
					$Fetch_Item = $this->pdo->prepare("SELECT * FROM `items` WHERE `Owner_Current` = ? AND `Item_ID` = ? LIMIT {$Limit}");
					$Fetch_Item->execute([$Owner_ID, $Item_ID]);
				}

				$Fetch_Item->setFetchMode(PDO::FETCH_ASSOC);
				$Item = $Fetch_Item->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return false;
			}

			if ( !$Item ) return false;

			return [
				"Row" => $Item['id'],
				"ID" => $Item['Item_ID'],
				"Name" => $Item['Item_Name'],
				"Category" => $Item['Item_Type'],
				"Owner" => $Item['Owner_Current'],
				"Quantity" => $Item['Quantity'],
				"Icon" => DOMAIN_SPRITES . "/Items/{$Item['Item_Name']}.png"
			];
		}

		/**
		 * Attach an item to a given Pokemon.
		 * @param $Item_ID :: The `Items`.`ID` of the given item.
		 * @param $Pokemon_ID :: The `Pokemon`.`ID` of the given Pokemon.
		 * @param $Owner_ID :: The `User`.`ID` of the given item's owner.
		 */
		public function Attach($Item_ID, $Pokemon_ID, $Owner_ID)
		{
			if ( !isset($Item_ID) || !isset($Pokemon_ID) )
				return false;

			$Item_Data = $this->FetchOwnedItem($Owner_ID, $Item_ID);
      if (!$Item_Data) return false;

			$Owner_Data = $this->userService->FetchUserData($Owner_ID);
      if (!$Owner_Data) return false;

			$Pokemon_Data = $this->pokemonService->GetPokemonData($Pokemon_ID);
      if (!$Pokemon_Data) return false;


			if ( $Item_Data['Quantity'] < 1 )
				return false;

			if ( $Item_Data['Owner'] != $Owner_Data['ID'] )
				return false;

			if ( $Pokemon_Data['Owner_Current'] != $Owner_Data['ID'] )
				return false;

			try
			{
        $this->pdo->beginTransaction();
				$Update_Pokemon = $this->pdo->prepare("UPDATE `pokemon` SET `Item` = ? WHERE `ID` = ?");
				$Update_Pokemon->execute([ $Item_Data['ID'], $Pokemon_ID ]);

				$Update_Item = $this->pdo->prepare("UPDATE `items` SET `Quantity` = `Quantity` - 1 WHERE `Owner_Current` = ? AND `Item_ID` = ?");
				$Update_Item->execute([ $Owner_Data['ID'], $Item_Data['ID'] ]);
        $this->pdo->commit();
			}
			catch ( PDOException $e )
			{
        $this->pdo->rollBack();
				HandleError($e);
        return false;
			}

			return true;
		}

		/**
		 * Unequip an item from a Pokemon.
		 * @param $Poke_ID :: The `Pokemon`.`ID` of the given Pokemon.
		 * @param $User_ID :: The `Users`.`ID` of the given Pokemon's owner.
		 */
		public function Unequip($Poke_ID, $User_ID)
		{
			$Owner_ID = Purify($User_ID);
			$Pokemon_ID = Purify($Poke_ID);

			$Pokemon = $this->pokemonService->GetPokemonData($Pokemon_ID);
      if (!$Pokemon) {
          return ['Message' => 'This Pok&eacute;mon could not be found.', 'Type' => 'error'];
      }

			$Item_Data = $this->FetchOwnedItem($Owner_ID, $Pokemon['Item_ID']);

			if ( $Pokemon['Owner_Current'] != $Owner_ID )
			{
				return [
					'Message' => 'You don\'t own this Pok&eacute;mon.',
					'Type' => 'error',
				];
			}
			else if ( $Pokemon['Item_ID'] == null || $Pokemon['Item_ID'] == 0 )
			{
				return [
					'Message' => 'This Pok&eacute;mon doesn\'t have an item equipped.',
					'Type' => 'error',
				];
			}
      else if ( !$Item_Data )
      {
        return [
          'Message' => 'Could not find details for the equipped item.',
          'Type' => 'error',
        ];
      }
			else if ( $Pokemon['Location'] == "Trade" )
			{
				return [
					'Message' => 'This Pok&eacute;mon is in a trade.',
					'Type' => 'error',
				];
			}
			else
			{
				try
				{
          $this->pdo->beginTransaction();
					$Update_Pokemon = $this->pdo->prepare("UPDATE `pokemon` SET `Item` = 0 WHERE `ID` = ?");
					$Update_Pokemon->execute([ $Pokemon['ID'] ]);

					$Update_Item = $this->pdo->prepare("UPDATE `items` SET `Quantity` = `Quantity` + 1 WHERE `Owner_Current` = ? AND `Item_ID` = ?");
					$Update_Item->execute([ $Owner_ID, $Item_Data['ID'] ]);
          $this->pdo->commit();
				}
				catch ( PDOException $e )
				{
          $this->pdo->rollBack();
					HandleError($e);
          return ['Message' => 'Error unequipping item.', 'Type' => 'error'];
				}

				return [
					'Message' => "You have detached your <b>{$Item_Data['Name']}</b> from <b>{$Pokemon['Display_Name']}</b>.",
					'Type' => 'success',
				];
			}
		}

		/**
		 * Add an item to the `items` database table.
		 * If the user already has the item, update the quantity.
		 * Else, create a new row.
		 */
		public function SpawnItem($User_ID, $Item_ID, $Quantity, $Subtract = false)
		{
			if ( !isset($User_ID) || !isset($Item_ID) || !isset($Quantity) )
			{
				die("Please specify the receiver's User ID, the Item ID, and Quantity of the item.");
			}
			else if ( $User_ID < 1 || $Item_ID < 1 || $Quantity < 1 )
			{
				die("Please specify a User ID, Item ID, and Quantity that are greater than 0.");
			}
			else
			{
				try
				{
					$Query_Row = $this->pdo->prepare("SELECT `id`, `Quantity` FROM `items` WHERE `Item_ID` = ? AND `Owner_Current` = ?");
					$Query_Row->execute([ $Item_ID, $User_ID ]);
					$Row_Data = $Query_Row->fetch(PDO::FETCH_ASSOC);

					$Item_Dex_Data = $this->FetchItemData($Item_ID);
          if (!$Item_Dex_Data) {
            die("Invalid Item ID provided to SpawnItem.");
          }

          $this->pdo->beginTransaction();
					if ( !$Row_Data )
					{
            if ($Subtract) {
              $this->pdo->rollBack();
              die("Attempting to subtract item quantity for an item the user does not possess.");
            }
						$Create_Row = $this->pdo->prepare("INSERT INTO `items` (`Item_ID`, `Item_Name`, `Item_Type`, `Owner_Current`, `Quantity`) VALUES (?, ?, ?, ?, ?)");
						$Create_Row->execute([ $Item_ID, $Item_Dex_Data['Name'], $Item_Dex_Data['Category'], $User_ID, $Quantity ]);
					}
					else
					{
						if ( $Subtract )
						{
              if ($Row_Data['Quantity'] < $Quantity) {
                $this->pdo->rollBack();
                die("Not enough items to subtract.");
              }
							$Update_Row = $this->pdo->prepare("UPDATE `items` SET `Quantity` = `Quantity` - ? WHERE `Item_ID` = ? AND `Owner_Current` = ?");
						}
						else
						{
							$Update_Row = $this->pdo->prepare("UPDATE `items` SET `Quantity` = `Quantity` + ? WHERE `Item_ID` = ? AND `Owner_Current` = ?");
						}
						$Update_Row->execute([ $Quantity, $Item_ID, $User_ID ]);
					}
          $this->pdo->commit();
				}
				catch( PDOException $e )
				{
          if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
          }
					HandleError($e);
				}
			}
		}
	}
