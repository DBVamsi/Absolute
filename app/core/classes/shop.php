<?php
/**
 * Service class for managing shops, including fetching shop data, stock, and handling purchases.
 */
	class Shop
	{
    /** @var PDO */
		private $pdo;
    /** @var PokemonService */
    private $pokemonService;
    /** @var User */
    private $userService;
    /** @var Item */
    private $itemService;

    /**
		 * Constructor for the Shop service.
		 *
     * @param PDO $pdo The PDO database connection object.
     * @param PokemonService $pokemonService Instance of the Pokemon service.
     * @param User $userService Instance of the User service.
     * @param Item $itemService Instance of the Item service.
		 */
		public function __construct(PDO $pdo, PokemonService $pokemonService, User $userService, Item $itemService)
		{
			$this->pdo = $pdo;
      $this->pokemonService = $pokemonService;
      $this->userService = $userService;
      $this->itemService = $itemService;
		}

    /**
     * Fetches specific shop data by its ID or unique place name.
     *
     * @param int|string $Shop_Identifier The ID or 'obtained_place' name of the shop.
     * @return array|false An associative array of shop data, or false if not found or on error.
     */
    public function FetchShopData(int|string $Shop_Identifier): array|false
    {
      if ( empty($Shop_Identifier) )
        return false;

      try
      {
        $Fetch_Shop = $this->pdo->prepare("SELECT * FROM `shops` WHERE `ID` = :id OR `obtained_place` = :place LIMIT 1");
        // Bind differently based on type, or cast ID to string if it could be numeric string for `obtained_place`
        if (is_numeric($Shop_Identifier)) {
            $Fetch_Shop->bindValue(':id', (int)$Shop_Identifier, PDO::PARAM_INT);
            $Fetch_Shop->bindValue(':place', (string)$Shop_Identifier); // Allow numeric obtained_place too
        } else {
            $Fetch_Shop->bindValue(':id', 0, PDO::PARAM_INT); // Ensure :id has a value if not numeric
            $Fetch_Shop->bindValue(':place', (string)$Shop_Identifier);
        }
        $Fetch_Shop->execute();
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
     * Fetches all active Pokémon being sold by a given shop.
     *
     * @param int $Shop_ID The ID of the shop.
     * @return array|false An array of shop Pokémon data, or false if not found or on error.
     */
    public function FetchShopPokemon(int $Shop_ID): array|false
    {
      if ( $Shop_ID <= 0 )
        return false;

      $Shop_Data = $this->FetchShopData($Shop_ID); // FetchShopData can take int or string
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
     * Fetches all items being sold by a given shop.
     *
     * @param int $Shop_ID The ID of the shop.
     * @return array|false An array of shop item data, or false if not found or on error.
     */
    public function FetchShopItems(int $Shop_ID): array|false
    {
      if ( $Shop_ID <= 0 )
        return false;

      $Shop_Data = $this->FetchShopData($Shop_ID); // FetchShopData can take int or string
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
     * Fetches the specific data of a given shop object (Item or Pokémon) by its shop entry ID.
     *
     * @param int $Object_ID The ID of the entry in `shop_items` or `shop_pokemon`.
     * @param string $Object_Type The type of object ('Item' or 'Pokemon').
     * @return array|false An associative array of the object's shop data, or false if not found/error.
     */
    public function FetchObjectData(int $Object_ID, string $Object_Type): array|false
    {
      if ( $Object_ID <= 0 || empty($Object_Type) )
        return false;

      $allowed_types = ['Item', 'Pokemon'];
      if ( !in_array($Object_Type, $allowed_types, true) )
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
     * Handles the purchase of an object (Pokémon or Item) from a shop by the current user.
     * Validates affordability, stock, and then processes the transaction by calling relevant services.
     * Uses `global $User_Data` for current user's ID and currency balances. This is a known issue
     * and ideally, $User_Data or specifically UserID and currency check methods would be passed or available via $this->userService.
     *
     * @param int $Object_ID The ID of the shop entry for the object (from `shop_items` or `shop_pokemon`).
     * @param string $Object_Type The type of object ('Item' or 'Pokemon').
     * @return array|bool For Pokémon, returns an array of created Pokémon data or false on failure.
     *                    For Items, returns true on success, false on failure.
     */
    public function PurchaseObject(int $Object_ID, string $Object_Type): array|bool
    {
      global $User_Data; // TODO: Refactor to remove global $User_Data. Pass UserID or use $this->userService.

      if ( $Object_ID <= 0 || empty($Object_Type) ) {
        error_log("PurchaseObject: Invalid Object_ID or Object_Type.");
        return false;
      }

      $allowed_object_types = ['Item', 'Pokemon'];
      if ( !in_array($Object_Type, $allowed_object_types, true) ) {
        error_log("PurchaseObject: Invalid Object_Type - {$Object_Type}.");
        return false;
      }

      $Object = $this->FetchObjectData($Object_ID, $Object_Type);
      if ( !$Object ) {
        error_log("PurchaseObject: Object ID {$Object_ID} of Type {$Object_Type} not found in shop stock.");
        return false;
      }

      $Shop_Data = $this->FetchShopData($Object['Obtained_Place']);
      if ( !$Shop_Data ) {
        error_log("PurchaseObject: Shop data for obtained_place '{$Object['Obtained_Place']}' not found.");
        return false;
      }

      // Validate object status and stock
      if ( empty($Object['Prices']) || ($Object['Active'] ?? 0) != 1 || ($Object['Remaining'] ?? 0) < 1 ) {
        error_log("PurchaseObject: Object ID {$Object_ID} is not purchasable (No price, inactive, or out of stock).");
        return false;
      }

      // Check affordability
      $Price_Array = $this->FetchPriceList($Object['Prices']);
      if (empty($Price_Array) || empty($Price_Array[0])) {
        error_log("PurchaseObject: Price array is empty or malformed for Object ID {$Object_ID}.");
        return false;
      }

      foreach ( $Price_Array[0] as $Currency => $Amount ) {
        // Ensure currency amount is numeric
        if (!is_numeric($Amount) || $Amount < 0) {
            error_log("PurchaseObject: Invalid amount {$Amount} for currency {$Currency} for Object ID {$Object_ID}.");
            return false;
        }
        if ( !isset($User_Data[$Currency]) || $User_Data[$Currency] < $Amount ) {
          // User cannot afford this item.
          return false;
        }
      }

      // Transaction for purchase (deduct currency, give item/pokemon, reduce stock, log)
      try {
        $this->pdo->beginTransaction();

        // Deduct currencies
        foreach ( $Price_Array[0] as $Currency => $Amount ) {
          if (!$this->userService->RemoveCurrency($User_Data['ID'], $Currency, (int)$Amount)) {
            throw new Exception("Failed to remove currency {$Currency} for User ID {$User_Data['ID']}.");
          }
        }

        // Reduce stock
        if (!$this->ReduceRemaining($Object['ID'], $Object_Type)) {
          throw new Exception("Failed to reduce stock for Object ID {$Object['ID']}.");
        }

        // Spawn item or Pokémon
        if ( $Object_Type == 'Pokemon' )
        {
          // ... (Pokémon creation logic as before, using $this->pokemonService)
          // This part is complex and could be a private helper method _finalizePokemonPurchase
          $Processed_Pokemon_Data = $this->_processPokemonPurchaseDetails($Object, $Shop_Data);

          $Spawn_Pokemon = $this->pokemonService->CreatePokemon(
            $User_Data['ID'],
            $Object['Pokedex_ID'],
            $Object['Alt_ID'],
            5, // Level - TODO: This should ideally come from shop_pokemon.Level if it exists
            $Processed_Pokemon_Data['Type_For_Creation'], // Use processed type (Shiny or Normal)
            $Processed_Pokemon_Data['Gender_For_Creation'], // Use processed gender
            $Shop_Data['Name'] // Obtained At
          );

          if ( !$Spawn_Pokemon ) {
            throw new Exception("Failed to create Pokémon for Object ID {$Object['ID']}.");
          }

          $this->InsertLog(
            $Shop_Data['Name'], null, $Spawn_Pokemon['PokeID'], $Object['Pokedex_ID'], $Object['Alt_ID'],
            $Processed_Pokemon_Data['Type_For_Creation'], $Processed_Pokemon_Data['Gender_For_Creation'],
            $Object['Prices'], $User_Data['ID'], time()
          );

          $this->pdo->commit();
          return [ // Return data consistent with original structure for AJAX handler
            'Display_Name' => $Spawn_Pokemon['Display_Name'], 'Stats' => $Spawn_Pokemon['Stats'],
            'IVs' => $Spawn_Pokemon['IVs'], 'EVs' => $Spawn_Pokemon['EVs'],
            'Nature' => $Spawn_Pokemon['Nature'], 'Sprite' => $Spawn_Pokemon['Sprite'],
            'Icon' => $Spawn_Pokemon['Icon'],
            'Shiny_Alert' => $Processed_Pokemon_Data['Shiny_Alert'],
            'Ungendered_Alert' => $Processed_Pokemon_Data['Ungendered_Alert'],
          ];
        }
        else // Item
        {
          // SpawnItem in ItemService should return bool or throw exception on failure
          // Assuming $Object['Item_ID'] holds the actual item_dex ID.
          if (!$this->itemService->SpawnItem($User_Data['ID'], $Object['Item_ID'], 1)) {
             throw new Exception("Failed to spawn item ID {$Object['Item_ID']} for User ID {$User_Data['ID']}.");
          }

          $this->InsertLog(
            $Shop_Data['Name'], $Object['Item_ID'], null, null, null, null, null,
            $Object['Prices'], $User_Data['ID'], time()
          );

          $this->pdo->commit();
          return true;
        }

      } catch (Exception $e) {
        if ($this->pdo->inTransaction()) {
          $this->pdo->rollBack();
        }
        error_log("PurchaseObject Error: " . $e->getMessage()); // Log detailed error
        return false; // Generic failure to client
      }
    }

    /**
     * Helper method to process Pokémon specific details during purchase (shiny, ungendered).
     * This could be part of a larger refactor of PurchaseObject.
     *
     * @param array $Object The shop Pokémon object data.
     * @param array $Shop_Data The shop's data.
     * @return array Details like 'Shiny_Alert', 'Ungendered_Alert', 'Type_For_Creation', 'Gender_For_Creation'.
     */
    private function _processPokemonPurchaseDetails(array $Object, array $Shop_Data): array
    {
        $Shiny_Alert = false;
        $Ungendered_Alert = false;
        $Type_For_Creation = $Object['Type']; // Type from shop_pokemon table (e.g. 'Normal', 'Shiny')
        $Gender_For_Creation = $Object['Gender'] ?? null; // Gender from shop_pokemon, if specified

        // If shop object type is 'Normal', perform shiny check based on shop odds
        if ($Object['Type'] === 'Normal' && isset($Shop_Data['Shiny_Odds'])) {
            if ($this->ShinyCheck((int)$Object['ID'], (int)$Shop_Data['Shiny_Odds'])) {
                $Shiny_Alert = true;
                $Type_For_Creation = 'Shiny'; // Override type for creation
            }
        }
        // If shop object type is already Shiny, it remains Shiny
        // $Type_For_Creation will be $Object['Type'] which could be 'Shiny'

        // Perform ungendered check if not already genderless by Pokedex default or shop override
        // This logic assumes GenerateGender gives a specific gender unless it's a genderless species.
        // If shop specifies a gender, that might override this.
        // For now, if shop doesn't specify gender, we might roll for ungendered.
        if (empty($Gender_For_Creation) || !in_array($Gender_For_Creation, ['Male', 'Female', 'Genderless', '(?)'], true) ) {
            if (isset($Shop_Data['Ungendered_Odds']) && $this->UngenderedCheck((int)$Object['ID'], (int)$Shop_Data['Ungendered_Odds'])) {
                $Ungendered_Alert = true;
                $Gender_For_Creation = '(?)';
            } else {
                // If not ungendered by shop roll, generate gender based on Pokedex species ratio
                $Gender_For_Creation = PokemonService::GenerateGender((int)$Object['Pokedex_ID'], (int)$Object['Alt_ID']);
            }
        } else if ($Gender_For_Creation === 'Fixed') { // Example if shop could define a fixed gender
             // $Gender_For_Creation = $Object['FixedGenderValue']; // This column doesn't exist, just an example
        }


        return [
            'Shiny_Alert' => $Shiny_Alert,
            'Ungendered_Alert' => $Ungendered_Alert,
            'Type_For_Creation' => $Type_For_Creation,
            'Gender_For_Creation' => $Gender_For_Creation,
        ];
    }


    /**
     * Parses a JSON string of prices into an array.
     *
     * @param string $Prices JSON string representing prices (e.g., '[{"Money": 1000, "Abso_Coins": 10}]').
     * @return array The decoded price array. Returns empty array if JSON is invalid.
     */
    public function FetchPriceList(string $Prices): array
    {
      // Basic validation: check if it's likely JSON
      if (empty($Prices) || !is_string($Prices) || $Prices[0] !== '[') {
          return []; // Return empty array for invalid input
      }
      $Price_List = json_decode($Prices, true);

      // Check for json_decode errors if necessary
      if (json_last_error() !== JSON_ERROR_NONE) {
          error_log("FetchPriceList: Invalid JSON provided - " . json_last_error_msg() . " Input: " . $Prices);
          return []; // Return empty array on decode error
      }

      return $Price_List ?? []; // Return empty array if null after decode
    }

    /**
     * Reduces the available stock of a shop object by one after a successful purchase.
     * This is a private helper method.
     *
     * @param int $Object_ID The ID of the shop entry (from `shop_items` or `shop_pokemon`).
     * @param string $Object_Type The type of object ('Item' or 'Pokemon').
     * @return bool True on success, false on failure.
     */
    private function ReduceRemaining(int $Object_ID, string $Object_Type): bool
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
     * Determines if a purchased Pokémon should be shiny based on shop odds.
     * This is a private helper method.
     *
     * @param int $Object_ID The ID of the shop Pokémon entry (for logging or specific checks if needed, though not directly used in current random roll).
     * @param int $Shiny_Odds The denominator for shiny odds (e.g., 4096 means 1/4096 chance).
     * @return bool True if the shiny check passes, false otherwise.
     */
    private function ShinyCheck(int $Object_ID, int $Shiny_Odds): bool
    {
      // $Object_ID is passed but not used in the current implementation of the check itself.
      // It could be used for logging or if specific items have different base shiny odds not covered by $Shiny_Odds.
      if ($Shiny_Odds <= 0) { // Odds of 0 or less mean it can't be shiny this way.
        return false;
      }
      return (mt_rand(1, $Shiny_Odds) === 1);
    }

    /**
     * Determines if a purchased Pokémon should be ungendered based on shop odds.
     * This is a private helper method.
     *
     * @param int $Object_ID The ID of the shop Pokémon entry.
     * @param int $Ungendered_Odds The denominator for ungendered odds.
     * @return bool True if the ungendered check passes, false otherwise.
     */
    private function UngenderedCheck(int $Object_ID, int $Ungendered_Odds): bool
    {
      // Similar to ShinyCheck, $Object_ID is available for more complex logic if needed.
      if ($Ungendered_Odds <= 0) { // Odds of 0 or less mean it can't be ungendered this way.
        return false;
      }
      return (mt_rand(1, $Ungendered_Odds) === 1);
    }

    /**
     * Inserts a new purchase log into the `shop_logs` database table.
     * This is a private helper method.
     *
     * @param string $Shop_Name Name of the shop.
     * @param int|null $Item_ID ID of the item purchased, or null if Pokémon.
     * @param int|null $Pokemon_ID Database ID of the Pokémon instance purchased, or null if item.
     * @param int|null $Pokemon_Pokedex_ID Pokedex ID of the Pokémon, or null if item.
     * @param int|null $Pokemon_Alt_ID Alternate form ID of the Pokémon, or null if item.
     * @param string|null $Pokemon_Type Type of the Pokémon (e.g., Shiny), or null if item.
     * @param string|null $Pokemon_Gender Gender of the Pokémon, or null if item.
     * @param string $Bought_With JSON string of prices/currencies used.
     * @param int $Bought_By User ID of the purchaser.
     * @param int $Timestamp Timestamp of the purchase.
     * @return void
     */
    private function InsertLog(
      string $Shop_Name,
      ?int $Item_ID,
      ?int $Pokemon_ID,
      ?int $Pokemon_Pokedex_ID,
      ?int $Pokemon_Alt_ID,
      ?string $Pokemon_Type,
      ?string $Pokemon_Gender,
      string $Bought_With,
      int $Bought_By,
      int $Timestamp
    ): void
    {
      // Basic validation for key parameters
      if (empty($Shop_Name) || $Bought_By <= 0 || $Timestamp <= 0) {
          error_log("InsertLog: Missing critical log data. Shop: {$Shop_Name}, User: {$Bought_By}");
          return;
      }

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
