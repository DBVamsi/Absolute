<?php
  class Player extends Map
  {
    private static $Instance;
    private $pdo;
    public $Map_ID;

    public function __construct(PDO $pdo)
    {
      $this->pdo = $pdo;
      if ( empty($_SESSION['EvoChroniclesRPG']['Maps']) )
      {
        $_SESSION['EvoChroniclesRPG']['Maps'] = [];
        $this->LoadLastMap();
      }
    }

    /**
     * Fetch the player's current instance.
     * NOTE: This Singleton pattern will need adjustment for PDO injection if GetInstance
     * is called before the main Player object is instantiated with PDO in session.php.
     * For this refactor, we assume $pdo is available when GetInstance is appropriately used.
     * A better approach might be to ensure GetInstance receives PDO or the main instance is always used.
     */
    public static function GetInstance(PDO $pdo = null)
    {
      if ( empty(self::$Instance) )
      {
        if ($pdo === null) {
          // This is problematic if called before session.php sets up the main $Player instance.
          // For now, this path might lead to issues if not handled carefully by callers.
          // Ideally, the global $pdo_instance from session.php should be passed here by any
          // code that might call GetInstance() before $Player_Class is initialized.
          // However, the current refactor focuses on objects already created in session.php.
          throw new Exception("PDO instance not provided to Player::GetInstance and no instance exists.");
        }
        self::$Instance = new Player($pdo);
      }
      else if ($pdo !== null && self::$Instance->pdo === null)
      {
        // If an instance exists but without PDO, and PDO is now provided.
        self::$Instance->pdo = $pdo;
      }


      return self::$Instance;
    }

    /**
     * Verify the player's interaction with a tile.
     *
     * @param {int} $x
     * @param {int} $y
     * @param {int} $z
     */
    public function CheckInteraction
    (
      int $x = null,
      int $y = null,
      int $z = null
    )
    {
      $Map_Objects = $_SESSION['EvoChroniclesRPG']['Maps']['Objects'];
      if ( empty($Map_Objects) )
        return false;

      $Check_Tile_Object = MapObject::GetObjectAtTile($Map_Objects, $x, $y, $z);
      if ( empty($Check_Tile_Object) )
        return false;

      $Is_Player_By_Tile = $this->IsNextToTile($x, $y, $z);
      if ( empty($Is_Player_By_Tile) )
        return false;

      return true;
    }

    /**
     * Process warping the user.
     */
    public function ProcessWarp
    (
      int $Tile_X,
      int $Tile_Y,
      int $Tile_Z,
      bool $Is_Warp_Tile = false
    )
    {
      if ( empty($Tile_X) || empty($Tile_Y) || empty($Tile_Z) || !$Is_Warp_Tile )
        return false;

      // Assumes $this->pdo is available for SetMap and SetPosition if they use it.
      // The Map constructor called by $this->SetPosition() will need $this (Player instance).
      if ( !$this->IsNextToTile($Tile_X, $Tile_Y + 1, $Tile_Z) )
        return false;

      $Map_Objects = $_SESSION['EvoChroniclesRPG']['Maps']['Objects'];
      $Get_Warp_Object = MapObject::GetObjectAtTile($Map_Objects, $Tile_X, $Tile_Y, $Tile_Z, 'warp');
      if ( !$Get_Warp_Object )
        return false;

      $Get_Designated_Warp_Map = MapObject::CheckPropertyByName($Get_Warp_Object->properties, 'warpTo');
      if ( !$Get_Designated_Warp_Map )
        return false;

      $this->SetMap($Get_Designated_Warp_Map->value);
      $this->SetPosition(); // This calls new Map() which needs refactoring to accept Player

      return $this->GetMap();
    }

    /**
     * Check if the player is next to a given tile.
     *
     * @param {int} $x
     * @param {int} $y
     * @param {int} $z
     */
    public function IsNextToTile
    (
      int $x = null,
      int $y = null,
      int $z = null
    )
    {
      $Player_Position = $_SESSION['EvoChroniclesRPG']['Maps']['Position'];

      $Adjacent_Coords = [
        [0, -1],
        [1, 0],
        [0, 1],
        [-1, 0],
      ];

      foreach ( ['up', 'right', 'down', 'left'] as $Index => $Direction )
      {
        if
        (
          $Player_Position['Map_X'] == $x + $Adjacent_Coords[$Index][0] &&
          $Player_Position['Map_Y'] == $y + $Adjacent_Coords[$Index][1] &&
          $Player_Position['Map_Z'] == $z
        )
          return true;
      }

      return false;
    }

    /**
     * Fetch the player's current position.
     */
    public function GetPosition()
    {
      global $User_Data; // User_Data is still global here

      if ( empty($_SESSION['EvoChroniclesRPG']['Maps']['Position']) )
      {
        try
        {
          $Fetch_Map_Position = $this->pdo->prepare("
            SELECT `Map_X`, `Map_Y`, `Map_Z`
            FROM `users`
            WHERE `ID` = ?
            LIMIT 1
          ");
          $Fetch_Map_Position->execute([ $User_Data['ID'] ]);
          $Fetch_Map_Position->setFetchMode(PDO::FETCH_ASSOC);
          $Map_Position = $Fetch_Map_Position->fetch();
        }
        catch ( \PDOException $e )
        {
          HandleError($e);
        }

        $_SESSION['EvoChroniclesRPG']['Maps']['Position'] = $Map_Position;
        return $Map_Position;
      }

      return $_SESSION['EvoChroniclesRPG']['Maps']['Position'];
    }

    /**
     * Set the player's position on the map,
     *
     * @param {int} $x
     * @param {int} $y
     * @param {int} $z
     */
    public function SetPosition
    (
      int $x = null,
      int $y = null,
      int $z = null
    )
    {
      global $User_Data; // User_Data is still global here

      if ( empty(func_get_args()) )
      {
        // This instantiation of Map will need to be refactored when Map constructor changes.
        // For now, we assume it works or Map's constructor is adapted.
        $Map = new Map($this, $this->GetMap()); // Pass $this (Player instance)
        $Map->GetMapObjects();
        $Spawn_Coords = $Map->GetSpawnCoords();

        return $this->SetPosition($Spawn_Coords['x'], $Spawn_Coords['y'], $Spawn_Coords['z']);
      }

      if
      (
        $User_Data['Map_Position']['Map_X'] == $x &&
        $User_Data['Map_Position']['Map_Y'] == $y &&
        $User_Data['Map_Position']['Map_Z'] == $z
      )
      {
        return true;
      }

      $_SESSION['EvoChroniclesRPG']['Maps']['Position'] = [
        'Map_X' => $x,
        'Map_Y' => $y,
        'Map_Z' => $z,
      ];

      try
      {
        $this->pdo->beginTransaction();

        $Set_Position = $this->pdo->prepare("
          UPDATE `users`
          SET `Map_X` = ?, `Map_Y` = ?, `Map_Z` = ?
          WHERE `ID` = ?
          LIMIT 1
        ");
        $Set_Position->execute([ $x, $y, $z, $User_Data['ID'] ]);

        $this->pdo->commit();
      }
      catch ( \PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
      }

      return true;
    }

    /**
     * Get the amount of steps until the player's next wild encounter,
     */
    public function GetStepsTillEncounter()
    {
      global $User_Data; // User_Data is still global here

      if ( empty($_SESSION['EvoChroniclesRPG']['Maps']['Map_Steps_To_Encounter']) )
        return $User_Data['Map_Steps_To_Encounter'];

      return $_SESSION['EvoChroniclesRPG']['Maps']['Map_Steps_To_Encounter'];
    }

    /**
     * Set the player's steps until their next wild encounter.
     *
     * @param {int} $Steps
     */
    public function SetStepsTillEncounter
    (
      int $Steps = -1
    )
    {
      global $User_Data; // User_Data is still global here

      try
      {
        $this->pdo->beginTransaction();

        $Update_Steps = $this->pdo->prepare("
          UPDATE `users`
          SET `Map_Steps_To_Encounter` = `Map_Steps_To_Encounter` + ?
          WHERE `ID` = ?
          LIMIT 1
        ");
        $Update_Steps->execute([ $Steps, $User_Data['ID'] ]);

        $this->pdo->commit();
      }
      catch ( \PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
      }

      // Update session variable to reflect the change immediately
      $_SESSION['EvoChroniclesRPG']['Maps']['Map_Steps_To_Encounter'] = $this->GetStepsTillEncounterFromDB($User_Data['ID']); // Helper to fetch current DB value
      return $_SESSION['EvoChroniclesRPG']['Maps']['Map_Steps_To_Encounter'];
    }

    /**
     * Helper function to get current steps from DB. Used after update.
     */
    private function GetStepsTillEncounterFromDB(int $UserID)
    {
        try
        {
            $Fetch_Steps = $this->pdo->prepare("SELECT `Map_Steps_To_Encounter` FROM `users` WHERE `ID` = ? LIMIT 1");
            $Fetch_Steps->execute([$UserID]);
            $Result = $Fetch_Steps->fetch();
            return $Result ? (int)$Result['Map_Steps_To_Encounter'] : 0;
        }
        catch (\PDOException $e)
        {
            HandleError($e);
            return 0; // Fallback
        }
    }

    /**
     * Fetch the player's current map.
     */
    public function GetMap()
    {
      return $_SESSION['EvoChroniclesRPG']['Maps']['Map_ID'];
    }

    /**
     * Set the player's current map.
     *
     * @param {string} $Map_ID
     */
    public function SetMap
    (
      string $Map_ID
    )
    {
      global $User_Data; // User_Data is still global here

      $this->Map_ID = $Map_ID;
      $_SESSION['EvoChroniclesRPG']['Maps']['Map_ID'] = $Map_ID;

      if ( !empty($_SESSION['EvoChroniclesRPG']['Maps']['Steps_To_Next_Encounter']) )
        unset($_SESSION['EvoChroniclesRPG']['Maps']['Steps_To_Next_Encounter']);

      if ( $User_Data['Map_ID'] == $Map_ID )
        return true;

      try
      {
        $this->pdo->beginTransaction();

        $Set_Map = $this->pdo->prepare("
          UPDATE `users`
          SET `Map_ID` = ?
          WHERE `ID` = ?
          LIMIT 1
        ");
        $Set_Map->execute([ $Map_ID, $User_Data['ID'] ]);

        $this->pdo->commit();
      }
      catch ( \PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
      }

      return true;
    }

    /**
     * Load the map the player was last on, including their position.
     */
    public function LoadLastMap()
    {
      global $User_Data; // User_Data is still global here

      if ( empty($User_Data['Map_ID']) )
      {
        // SetPosition will call new Map() which needs player instance
        $this->SetPosition();
      }
      else
      {
        $this->SetMap($User_Data['Map_ID']);
        $this->SetPosition($User_Data['Map_Position']['Map_X'], $User_Data['Map_Position']['Map_Y'], $User_Data['Map_Position']['Map_Z']);
      }
    }

    /**
     * Fetch the player's map level and experience.
     */
    public function GetMapLevelAndExp()
    {
      global $User_Data; // User_Data is still global here

      return [
        'Map_Level' => FetchLevel($User_Data['Map_Experience'], 'Map'),
        'Map_Experience' => $User_Data['Map_Experience'],
      ];
    }

    /**
     * Update the user's map experience.
     *
     * @param {int} $Exp_Earned
     */
    public function UpdateMapExperience
    (
      int $Exp_Earned
    )
    {
      global $User_Data; // User_Data is still global here

      try
      {
        $this->pdo->beginTransaction();

        $Update_Map_Exp = $this->pdo->prepare("
          UPDATE `users`
          SET `Map_Experience` = `Map_Experience` + ?
          WHERE `ID` = ?
          LIMIT 1
        ");
        $Update_Map_Exp->execute([ $Exp_Earned, $User_Data['ID'] ]);

        $this->pdo->commit();
      }
      catch ( \PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e); // It's good practice to handle or log the error.
      }
    }
  }
