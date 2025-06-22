<?php
/**
 * Service class for managing items, including fetching item data, user inventory, and item interactions.
 */
	class Item
	{
    /** @var PDO */
		private $pdo;
    /** @var PokemonService */
    private $pokemonService;
    /** @var User */
    private $userService;

		/**
		 * Constructor for the Item service.
		 *
     * @param PDO $pdo The PDO database connection object.
     * @param PokemonService $pokemonService Instance of the Pokemon service.
     * @param User $userService Instance of the User service.
		 */
		public function __construct(PDO $pdo, PokemonService $pokemonService, User $userService)
		{
			$this->pdo = $pdo;
      $this->pokemonService = $pokemonService;
      $this->userService = $userService;
		}

		/**
		 * Fetches the data of a specific item from the item pokedex (`item_dex`).
		 *
     * @param int $Item_ID The ID of the item to fetch.
     * @return array|false An associative array of item data, or false if not found or on error.
		 */
		public function FetchItemData(int $Item_ID): array|false
		{
      if ($Item_ID <= 0) return false;

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
		 * Fetches item(s) owned by a user from their inventory (`items` table).
		 * Can fetch a specific item or a list of items.
		 *
     * @param int $Owner_ID The ID of the user whose items to fetch.
     * @param int|null $Item_ID Optional. The specific item ID to fetch. If null, fetches based on limit.
     * @param int $Limit The maximum number of items to fetch if $Item_ID is null. Defaults to 1.
     * @return array|false An associative array of the owned item's data (if specific item found or limit 1)
     *                     or an array of items (if limit > 1 and $Item_ID is null - though current query only fetches one).
     *                     Returns false on error or if not found.
		 */
		public function FetchOwnedItem(int $Owner_ID, ?int $Item_ID = null, int $Limit = 1): array|false
		{
			if ( $Owner_ID <= 0 )
				return false;

      // Ensure Limit is positive.
      $Limit = max(1, (int)$Limit);

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
		 * Attaches an item from a user's inventory to one of their Pokémon.
		 * Decrements item quantity and updates Pokémon's held item.
		 *
		 * @param int $Item_ID The ID of the item in the `item_dex` (used to identify the item type).
		 * @param int $Pokemon_ID The database ID of the Pokémon to attach the item to.
		 * @param int $Owner_ID The ID of the user who owns both the item and the Pokémon.
		 * @return bool True on success, false on failure.
		 */
		public function Attach(int $Item_ID, int $Pokemon_ID, int $Owner_ID): bool
		{
			if ( $Item_ID <= 0 || $Pokemon_ID <= 0 || $Owner_ID <= 0 )
				return false;

      // Fetch the specific owned item instance to check quantity and ownership.
      // FetchOwnedItem currently fetches a single item. This is appropriate here.
			$Owned_Item_Data = $this->FetchOwnedItem($Owner_ID, $Item_ID);
      if (!$Owned_Item_Data) {
        error_log("AttachItem: User {$Owner_ID} does not own item ID {$Item_ID} or item not found.");
        return false;
      }

			$Owner_Data = $this->userService->FetchUserData($Owner_ID);
      if (!$Owner_Data) {
        error_log("AttachItem: Owner data for User ID {$Owner_ID} not found.");
        return false;
      }

			$Pokemon_Data = $this->pokemonService->GetPokemonData($Pokemon_ID);
      if (!$Pokemon_Data) {
        error_log("AttachItem: Pokemon ID {$Pokemon_ID} not found.");
        return false;
      }

			if ( $Owned_Item_Data['Quantity'] < 1 ) {
        error_log("AttachItem: User {$Owner_ID} has insufficient quantity of item ID {$Item_ID}.");
				return false;
      }

      // Verify ownership of item and Pokémon
			if ( $Owned_Item_Data['Owner'] != $Owner_ID ) { // Redundant if FetchOwnedItem correctly scopes by Owner_ID
        error_log("AttachItem: Item ID {$Item_ID} not owned by User ID {$Owner_ID}.");
				return false;
      }
			if ( $Pokemon_Data['Owner_Current'] != $Owner_ID ) {
        error_log("AttachItem: Pokemon ID {$Pokemon_ID} not owned by User ID {$Owner_ID}.");
				return false;
      }
      if ( !empty($Pokemon_Data['Item_ID']) ) {
        error_log("AttachItem: Pokemon ID {$Pokemon_ID} is already holding an item.");
        return false; // Pokemon already holding an item
      }


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
		/**
		 * Unequips an item from a Pokémon and returns it to the user's inventory.
		 * Increases item quantity and clears Pokémon's held item.
		 *
		 * @param int $Pokemon_ID The database ID of the Pokémon.
		 * @param int $User_ID The ID of the user who owns the Pokémon.
		 * @return array An associative array with 'Message' and 'Type' (success/error).
		 */
		public function Unequip(int $Pokemon_ID, int $User_ID): array
		{
      // Parameters are now type-hinted as int, no need for Purify.
      // Basic validation
      if ($Pokemon_ID <= 0 || $User_ID <= 0) {
        return ['Message' => 'Invalid Pokémon or User ID.', 'Type' => 'error'];
      }

			$Pokemon = $this->pokemonService->GetPokemonData($Pokemon_ID);
      if (!$Pokemon) {
          return ['Message' => 'This Pok&eacute;mon could not be found.', 'Type' => 'error'];
      }

      // Ensure the Pokémon belongs to the user making the request
			if ( $Pokemon['Owner_Current'] != $User_ID )
			{
				return [
					'Message' => 'You don\'t own this Pok&eacute;mon.',
					'Type' => 'error',
				];
			}
			else if ( empty($Pokemon['Item_ID']) ) // Check if Item_ID is null or 0 (or empty string)
			{
				return [
					'Message' => 'This Pok&eacute;mon doesn\'t have an item equipped.',
					'Type' => 'error',
				];
			}

      // Fetch the item data based on the item ID the Pokemon is holding
			$Item_Data = $this->FetchItemData($Pokemon['Item_ID']); // Use FetchItemData to get item details from item_dex
      if ( !$Item_Data )
      {
        // This case implies the Pokemon.Item field has an ID that doesn't exist in item_dex, data integrity issue.
        error_log("UnequipItem: Item ID {$Pokemon['Item_ID']} held by Pokemon ID {$Pokemon_ID} not found in item_dex.");
        return [
          'Message' => 'The equipped item could not be identified. Please contact support.',
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

          // Use SpawnItem to add the item back to inventory. It handles existing vs new rows.
          // The item ID to add back is $Pokemon['Item_ID'].
					$this->SpawnItem($User_ID, $Pokemon['Item_ID'], 1);

          $this->pdo->commit();
				}
				catch ( PDOException $e )
				{
          if ($this->pdo->inTransaction()) $this->pdo->rollBack();
					HandleError($e);
          return ['Message' => 'An error occurred while unequipping the item.', 'Type' => 'error'];
				}

        // Use htmlspecialchars for display names in messages
        $Item_Name_Escaped = htmlspecialchars($Item_Data['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $Pokemon_Display_Name_Escaped = htmlspecialchars($Pokemon['Display_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

				return [
					'Message' => "You have detached your <b>{$Item_Name_Escaped}</b> from <b>{$Pokemon_Display_Name_Escaped}</b>.",
					'Type' => 'success',
				];
			}
		}

		/**
		/**
		 * Adds or removes an item from a user's inventory.
		 * If the user already has the item, it updates the quantity. Otherwise, it creates a new row.
		 * Uses `die()` for critical errors, which should be refactored to return statuses or throw exceptions.
		 *
     * @param int $User_ID The ID of the user.
     * @param int $Item_ID The ID of the item (from `item_dex`).
     * @param int $Quantity The quantity to add (or subtract if $Subtract is true). Must be positive.
     * @param bool $Subtract If true, subtracts the quantity. Defaults to false (add).
     * @return void Currently uses `die()` on error, otherwise no explicit return for success. Should be refactored.
		 */
		public function SpawnItem(int $User_ID, int $Item_ID, int $Quantity, bool $Subtract = false): void
		{
      // Basic validation
			if ( $User_ID <= 0 || $Item_ID <= 0 || $Quantity <= 0 )
			{
        // TODO: Refactor die() to return status or throw exception
				die("SpawnItem Error: User ID, Item ID, and Quantity must be positive.");
			}

			// Fetch item details from item_dex to ensure it's a valid item
			$Item_Dex_Data = $this->FetchItemData($Item_ID);
      if (!$Item_Dex_Data) {
        // TODO: Refactor die()
        die("SpawnItem Error: Invalid Item ID '{$Item_ID}' provided.");
      }

			try
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
