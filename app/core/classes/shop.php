<?php
	class Shop
	{
		private $pdo;
    private $pokemonService;
    private $userService;
    private $itemService;

    /**
     * Construct and initialize the class.
     */
		public function __construct(PDO $pdo, PokemonService $pokemonService, User $userService, Item $itemService)
		{
			$this->pdo = $pdo;
      $this->pokemonService = $pokemonService;
      $this->userService = $userService;
      $this->itemService = $itemService;
		}

    /**
     * Fetch specific shop data.
     * @param int|string $Shop_ID
     */
    public function FetchShopData
    (
      $Shop_ID
    )
    {
      if ( !$Shop_ID )
        return false;

      try
      {
        $Fetch_Shop = $this->pdo->prepare("SELECT * FROM `shops` WHERE `ID` = ? OR `obtained_place` = ? LIMIT 1");
        $Fetch_Shop->execute([ $Shop_ID, $Shop_ID ]);
        $Fetch_Shop->setFetchMode(PDO::FETCH_ASSOC);
        $Shop = $Fetch_Shop->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      if ( !$Shop )
        return false;

      return [
        'ID' => $Shop['ID'],
        'Name' => $Shop['Name'],
        'Description' => $Shop['Description'],
        'Obtained_Place' => $Shop['Obtained_Place'],
        'Shiny_Odds' => $Shop['Shiny_Odds'],
        'Ungendered_Odds' => $Shop['Ungendered_Odds'],
      ];
    }

    /**
     * Fetch all Pokemon that are being sold by a given shop.
     * @param int $Shop_ID
     */
    public function FetchShopPokemon
    (
      int $Shop_ID
    )
    {
      if ( !$Shop_ID )
        return false;

      $Shop_Data = $this->FetchShopData($Shop_ID);
      if ( !$Shop_Data )
        return false;

      try
      {
        $Fetch_Shop_Objects = $this->pdo->prepare("SELECT * FROM `shop_pokemon` WHERE `obtained_place` = ? AND `Active` = 1");
        $Fetch_Shop_Objects->execute([ $Shop_Data['Obtained_Place'] ]);
        $Fetch_Shop_Objects->setFetchMode(PDO::FETCH_ASSOC);
        $Shop_Objects = $Fetch_Shop_Objects->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      if ( !$Shop_Objects )
        return false;

      return $Shop_Objects;
    }

    /**
     * Fetch all items that are being sold by a given shop.
     * @param int $Shop_ID
     */
    public function FetchShopItems
    (
      int $Shop_ID
    )
    {
      if ( !$Shop_ID )
        return false;

      $Shop_Data = $this->FetchShopData($Shop_ID);
      if ( !$Shop_Data )
        return false;

      try
      {
        $Fetch_Shop_Objects = $this->pdo->prepare("SELECT * FROM `shop_items` WHERE `obtained_place` = ?");
        $Fetch_Shop_Objects->execute([ $Shop_Data['Obtained_Place'] ]);
        $Fetch_Shop_Objects->setFetchMode(PDO::FETCH_ASSOC);
        $Shop_Objects = $Fetch_Shop_Objects->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      if ( !isset($Shop_Objects) || count($Shop_Objects) === 0 )
        return false;

      return $Shop_Objects;
    }

    /**
     * Fetch the specific data of a given object.
     * @param int $Object_ID
     * @param string $Object_Type
     */
    public function FetchObjectData
    (
      int $Object_ID,
      string $Object_Type
    )
    {
      if ( !$Object_ID || !$Object_Type )
        return false;

      if ( !in_array($Object_Type, ['Item', 'Pokemon']) )
        return false;

      try
      {
        if ( $Object_Type == 'Item' )
          $Fetch_Object_Data = $this->pdo->prepare("SELECT * FROM `shop_items` WHERE `ID` = ? LIMIT 1");
        else
          $Fetch_Object_Data = $this->pdo->prepare("SELECT * FROM `shop_pokemon` WHERE `ID` = ? LIMIT 1");

        $Fetch_Object_Data->execute([ $Object_ID ]);
        $Fetch_Object_Data->setFetchMode(PDO::FETCH_ASSOC);
        // fetchAll() was incorrect here, should be fetch() for LIMIT 1
        $Object_Data = $Fetch_Object_Data->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      if ( !$Object_Data )
        return false;

      return $Object_Data; // Return single row
    }

    /**
     * Purchase a given object from a shop, given it's object ID and type.
     * @param int $Object_ID
     * @param string $Object_Type
     */
    public function PurchaseObject
    (
      int $Object_ID,
      string $Object_Type
    )
    {
      global $User_Data; // $User_Data is still global, for things like $User_Data['ID'] and $User_Data[$Currency]

      if ( !$Object_ID || !$Object_Type )
        return false;

      // FetchObjectData now returns a single row or false
      $Object = $this->FetchObjectData($Object_ID, $Object_Type);
      if ( !$Object ) // If $Object_Data was meant to be the single row, this check is fine.
        return false;

      // Note: The original code fetched $Object_Data as fetchAll, then did another fetch for $Object.
      // I've simplified FetchObjectData to return a single row (or false).
      // So, $Object is now $Object_Data from the simplified method.

      $Shop_Data = $this->FetchShopData($Object['Obtained_Place']);
      if ( !$Shop_Data )
        return false;

      if
      (
        !$Object['Prices'] ||
        !$Object['Active'] ||
        $Object['Remaining'] < 1
      )
        return false;

      $Can_Afford = true;
      $Price_Array = $this->FetchPriceList($Object['Prices']);
      foreach ( $Price_Array[0] as $Currency => $Amount )
      {
        if ( !isset($User_Data[$Currency]) || $User_Data[$Currency] < $Amount ) // Check if currency key exists in User_Data
        {
          $Can_Afford = false;
          break;
        }
      }

      if ( !$Can_Afford )
        return false;

      if ( $Object_Type == 'Pokemon' )
      {
        $Shiny_Alert = false;
        $Ungendered_Alert = false;

        if ( $Object['Type'] == 'Normal' )
        {
          $Shiny_Check = $this->ShinyCheck($Object['ID'], $Shop_Data['Shiny_Odds']);
          if ( $Shiny_Check )
          {
            $Shiny_Alert = true;
            $Object['Type'] = 'Shiny';
          }
          else
            $Object['Type'] = 'Normal';
        }

        $Ungendered_Check = $this->UngenderedCheck($Object['ID'], $Shop_Data['Ungendered_Odds']);
        if ( $Ungendered_Check )
        {
          $Ungendered_Alert = true;
          $Object['Gender'] = '(?)';
        }
        else
          // GenerateGender is static on PokemonService
          $Object['Gender'] = PokemonService::GenerateGender($Object['Pokedex_ID'], $Object['Alt_ID']);

        $Spawn_Pokemon = $this->pokemonService->CreatePokemon(
          $User_Data['ID'],
          $Object['Pokedex_ID'],
          $Object['Alt_ID'],
          5, // Assuming level 5 for shop Pokemon, this could be data-driven
          $Object['Type'],
          $Object['Gender'],
          $Shop_Data['Name']
        );

        if ( !$Spawn_Pokemon )
          return false;

        $this->ReduceRemaining($Object['ID'], $Object_Type);

        foreach ( $Price_Array[0] as $Currency => $Amount )
          $this->userService->RemoveCurrency($User_Data['ID'], $Currency, $Amount);

        $this->InsertLog(
          $Shop_Data['Name'],
          null,
          $Spawn_Pokemon['PokeID'],
          $Object['Pokedex_ID'],
          $Object['Alt_ID'],
          $Object['Type'],
          $Object['Gender'],
          $Object['Prices'],
          $User_Data['ID'],
          time()
        );

        return [
          'Display_Name' => $Spawn_Pokemon['Display_Name'],
          'Stats' => $Spawn_Pokemon['Stats'],
          'IVs' => $Spawn_Pokemon['IVs'],
          'EVs' => $Spawn_Pokemon['EVs'],
          'Nature' => $Spawn_Pokemon['Nature'],
          'Sprite' => $Spawn_Pokemon['Sprite'],
          'Icon' => $Spawn_Pokemon['Icon'],
          'Shiny_Alert' => $Shiny_Alert,
          'Ungendered_Alert' => $Ungendered_Alert,
        ];
      }
      else // Item
      {
        $Spawn_Item = $this->itemService->SpawnItem($User_Data['ID'], $Object['Item_ID'], 1); // Assuming Item_ID is the correct key for shop_items
        if ( !$Spawn_Item )
          return false;

        $this->ReduceRemaining($Object['ID'], $Object_Type);

        foreach ( $Price_Array[0] as $Currency => $Amount )
          $this->userService->RemoveCurrency($User_Data['ID'], $Currency, $Amount);

        $this->InsertLog(
          $Shop_Data['Name'],
          $Object['Item_ID'], // Assuming Item_ID for items
          null, null, null, null, null,
          $Object['Prices'],
          $User_Data['ID'],
          time()
        );

        return true;
      }
    }

    /**
     * Parse a given string of prices.
     * @param string $Prices
     */
    public function FetchPriceList
    (
      string $Prices
    )
    {
      $Price_List = json_decode($Prices, true);

      return $Price_List;
    }

    /**
     * Reduce the available amount of objects remaining by one.
     * @param int $Object_ID
     * @param string $Object_Type
     */
    private function ReduceRemaining
    (
      int $Object_ID,
      string $Object_Type
    )
    {
      if ( !$Object_ID || !$Object_Type )
        return false;

      // FetchObjectData already called in PurchaseObject, $Object is available.
      // Re-fetching here is redundant if $Object_Data in PurchaseObject was the single fetched object.
      // For safety, if it's critical to have the absolute latest, keep it, but consider optimization.
      // $Object_Fresh_Data = $this->FetchObjectData($Object_ID, $Object_Type);
      // if ( !$Object_Fresh_Data )
      //  return false;

      try
      {
        if ( $Object_Type == 'Item' )
          $Reduce_Amount = $this->pdo->prepare("UPDATE `shop_items` SET `Remaining` = `Remaining` - 1 WHERE `ID` = ?");
        else
          $Reduce_Amount = $this->pdo->prepare("UPDATE `shop_pokemon` SET `Remaining` = `Remaining` - 1 WHERE `ID` = ?");

        $Reduce_Amount->execute([ $Object_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      return true;
    }

    /**
     * Determine if the purchased object is shiny.
     * @param int $Object_ID
     */
    private function ShinyCheck
    (
      int $Object_ID,
      int $Shiny_Odds
    )
    {
      if ( !$Object_ID || !$Shiny_Odds ) // Shiny_Odds can be 0, meaning never shiny from this shop.
        return false;
      if ($Shiny_Odds <= 0) return false; // Prevent division by zero or negative odds

      if ( mt_rand(1, $Shiny_Odds) == 1 )
        return true;
      else
        return false;
    }

    /**
     * Determine if the purchased object is ungendered.
     * @param int $Object_ID
     */
    private function UngenderedCheck
    (
      int $Object_ID,
      int $Ungendered_Odds
    )
    {
      if ( !$Object_ID || !$Ungendered_Odds ) // Ungendered_Odds can be 0.
        return false;
      if ($Ungendered_Odds <= 0) return false;


      if ( mt_rand(1, $Ungendered_Odds) == 1 )
        return true;
      else
        return false;
    }

    /**
     * Insert a new purchase log into the `shop_logs` database table.
     *
     * @param {string} $Shop_Name
     * @param {int} $Item_ID
     * @param {int} $Pokemon_ID
     * @param {int} $Pokemon_Pokedex_ID
     * @param {int} $Pokemon_Alt_ID
     * @param {int} $Pokemon_Type
     * @param {int} $Pokemon_Gender
     * @param {string} $Bought_With
     * @param {int} $Bought_By
     * @param {int} $Timestamp
     */
    private function InsertLog
    (
      $Shop_Name,
      $Item_ID,
      $Pokemon_ID,
      $Pokemon_Pokedex_ID,
      $Pokemon_Alt_ID,
      $Pokemon_Type,
      $Pokemon_Gender,
      $Bought_With,
      $Bought_By,
      $Timestamp
    )
    {
      try
      {
        $this->pdo->beginTransaction();

        $Insert_Shop_Log = $this->pdo->prepare("
          INSERT INTO `shop_logs` (
            `Shop_Name`, `Item_ID`, `Pokemon_ID`, `Pokemon_Pokedex_ID`, `Pokemon_Alt_ID`, `Pokemon_Type`, `Pokemon_Gender`, `Bought_With`, `Bought_By`, `Timestamp`
          ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
        ");
        // func_get_args() might be risky if method signature changes. Explicitly list args.
        $Insert_Shop_Log->execute([
          $Shop_Name, $Item_ID, $Pokemon_ID, $Pokemon_Pokedex_ID, $Pokemon_Alt_ID,
          $Pokemon_Type, $Pokemon_Gender, $Bought_With, $Bought_By, $Timestamp
        ]);

        $this->pdo->commit();
      }
      catch ( \PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
      }
    }
  }
