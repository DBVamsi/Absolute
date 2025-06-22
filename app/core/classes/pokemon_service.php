<?php
/**
 * Handles Pokémon related operations such as creation, data fetching, and management.
 */
  class PokemonService
  {
    /**
     * @var PDO The database connection object.
     */
    private $pdo;

    /**
     * Constructor for PokemonService.
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo)
    {
      $this->pdo = $pdo;
    }

    /**
     * Creates a new Pokémon for a given user.
     * Determines roster slot or box placement. Generates gender, ability, nature, IVs if not provided.
     *
     * @param int $Owner_ID The ID of the user who will own this Pokémon.
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon species.
     * @param int $Alt_ID The alternate form ID of the Pokémon.
     * @param int $Level The level of the Pokémon. Defaults to 5.
     * @param string $Type The type of the Pokémon (e.g., Normal, Shiny). Defaults to "Normal".
     * @param string|null $Gender The gender of the Pokémon. Auto-generated if null.
     * @param string $Obtained_At Describes where/how the Pokémon was obtained. Defaults to "Unknown".
     * @param string|null $Nature The nature of the Pokémon. Auto-generated if null.
     * @param string|null $IVs Comma-separated string of IV values. Auto-generated if null.
     * @param string|null $EVs Comma-separated string of EV values. Defaults to "0,0,0,0,0,0" if null.
     * @return array|false An associative array of the created Pokémon's data, or false on failure.
     */
    public function CreatePokemon(
      int $Owner_ID,
      int $Pokedex_ID,
      int $Alt_ID,
      $Level = 5,
      $Type = "Normal",
      $Gender = null,
      $Obtained_At = "Unknown",
      $Nature = null,
      $IVs = null,
      ?string $EVs = null
    )
    {
      // Fetch base Pokédex data for the species
      $Pokemon = $this->GetPokedexData($Pokedex_ID, $Alt_ID, $Type);
      if ( !$Pokemon )
        return false;

      if ( !is_numeric($Level) )
        $Level = 5;

      if ( $Type != "Normal" )
        $Display_Name = $Type . $Pokemon['Name'];
      else
        $Display_Name = $Pokemon['Name'];

      if ( empty($Gender) )
        $Gender = $this->GenerateGender($Pokemon['Pokedex_ID'], $Pokemon['Alt_ID']);

      $Ability = $this->GenerateAbility($Pokemon['Pokedex_ID'], $Pokemon['Alt_ID']);

      $Poke_Images = $this->GetSprites($Pokedex_ID, $Alt_ID, $Type);

      try
      {
        $Query_Party = $this->pdo->prepare("
          SELECT DISTINCT(`Slot`)
          FROM `pokemon`
          WHERE `Owner_Current` = ? AND (Slot = 1 OR Slot = 2 OR Slot = 3 OR Slot = 4 OR Slot = 5 OR Slot = 6) AND `Location` = 'Roster'
          LIMIT 6
        ");
        $Query_Party->execute([
          $Owner_ID
        ]);
        $Query_Party->setFetchMode(PDO::FETCH_ASSOC);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      $Slots_Used = [0, 0, 0, 0, 0, 0, 0];
      while ( $Party = $Query_Party->fetch() )
        $Slots_Used[$Party['Slot']] = 1;

      $First_Empty_Slot = array_search(0, $Slots_Used);
      if ( $First_Empty_Slot === false )
      {
        $Location = 'Box';
        $Slot = 7;
      }
      else
      {
        $Location = 'Roster';
        $Slot = $First_Empty_Slot;
      }

      $Experience = self::FetchExperience($Level, 'Pokemon');

      if ( empty($IVs) )
      {
        $IVs = mt_rand(0, 31) . "," . mt_rand(0, 31) . "," . mt_rand(0, 31) . "," . mt_rand(0, 31) . "," . mt_rand(0, 31) . "," . mt_rand(0, 31);
      }

      if ( empty($EVs) )
      {
        $EVs = "0,0,0,0,0,0";
      }

      if ( empty($Nature) )
      {
        $Nature = self::GenerateNature();
      }

      try
      {
        $this->pdo->beginTransaction();

        $Pokemon_Create = $this->pdo->prepare("
          INSERT INTO `pokemon` (
            `Pokedex_ID`, `Alt_ID`, `Name`, `Forme`, `Type`, `Experience`, `Location`, `Slot`, `Owner_Current`, `Owner_Original`, `Gender`, `IVs`, `EVs`, `Nature`, `Creation_Date`, `Creation_Location`, `Ability`
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $Pokemon_Create->execute([
          $Pokedex_ID, $Alt_ID, $Pokemon['Name'], $Pokemon['Forme'],
          $Type, $Experience, $Location, $Slot, $Owner_ID, $Owner_ID, $Gender,
          $IVs, $EVs, $Nature, time(), $Obtained_At, $Ability
        ]);
        $Poke_DB_ID = $this->pdo->lastInsertId();

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return false;
      }

      return [
        'Name' => $Pokemon['Name'],
        'Forme' => $Pokemon['Forme'],
        'Display_Name' => $Display_Name,
        'Exp' => $Experience,
        'Gender' => $Gender,
        'Location' => $Location,
        'Slot' => $Slot,
        'PokeID' => $Poke_DB_ID,
        'Stats' => $Pokemon['Base_Stats'],
        'IVs' => explode(',', $IVs),
        'EVs' => explode(',', $EVs),
        'Nature' => $Nature,
        'Ability' => $Ability,
        'Sprite' => $Poke_Images['Sprite'],
        'Icon' => $Poke_Images['Icon'],
      ];
    }

    /**
     * Fetches the possible abilities for a given Pokémon species and form.
     *
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon.
     * @return array|false Associative array of abilities (Ability_1, Ability_2, Hidden_Ability) or false if not found.
     */
    public function GetAbilities(int $Pokedex_ID, int $Alt_ID)
    {
      try
      {
        $Fetch_Abilities = $this->pdo->prepare("
          SELECT `Ability_1`, `Ability_2`, `Hidden_Ability`
          FROM `pokedex`
          WHERE `Pokedex_ID` = ? AND `Alt_ID` = ?
          LIMIT 1
        ");
        $Fetch_Abilities->execute([ $Pokedex_ID, $Alt_ID ]);
        $Fetch_Abilities->setFetchMode(PDO::FETCH_ASSOC);
        $Abilities = $Fetch_Abilities->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Abilities )
        return false;

      return $Abilities;
    }

    /**
     * Fetches the base stats for a given Pokémon species and form.
     *
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon.
     * @return array|false Associative array of base stats (HP, Attack, etc.) or false if not found.
     */
    public function GetBaseStats(int $Pokedex_ID, int $Alt_ID)
    {
      try
      {
        $Fetch_Stats = $this->pdo->prepare("
          SELECT `HP`, `Attack`, `Defense`, `SpAttack`, `SpDefense`, `Speed`
          FROM `pokedex`
          WHERE `Pokedex_ID` = ? AND `Alt_ID` = ?
          LIMIT 1
        ");
        $Fetch_Stats->execute([ $Pokedex_ID, $Alt_ID ]);
        $Fetch_Stats->setFetchMode(PDO::FETCH_ASSOC);
        $Stats = $Fetch_Stats->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Stats )
        return false;

      return $Stats;
    }

    public function GetCurrentStats(int $Pokemon_ID)
    {
      try
      {
        $Get_Pokemon_Data = $this->pdo->prepare("
          SELECT `Pokedex_ID`, `Alt_ID`, `Nature`, `Type`, `EVs`, `IVs`, `Experience`
          FROM `pokemon`
          WHERE `ID` = ?
          LIMIT 1
        ");
        $Get_Pokemon_Data->execute([ $Pokemon_ID ]);
        $Get_Pokemon_Data->setFetchMode(PDO::FETCH_ASSOC);
        $Pokemon = $Get_Pokemon_Data->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !isset($Pokemon) )
        return false;

      switch($Pokemon['Type'])
      {
        case 'Normal': $StatBonus = 0; break;
        case 'Shiny': $StatBonus = 5; break;
        case 'Sunset': $StatBonus = 10; break;
        default: $StatBonus = 0; break;
      }

      $Base_Stats = $this->GetBaseStats($Pokemon['Pokedex_ID'], $Pokemon['Alt_ID']);
      $Level = self::FetchLevel($Pokemon['Experience'], 'Pokemon');
      $EVs = explode(',', $Pokemon['EVs']);
      $IVs = explode(',', $Pokemon['IVs']);

      $Stats = [
        self::CalculateStat('HP', floor($Base_Stats['HP'] + $StatBonus), $Level, $IVs[0], $EVs[0], $Pokemon['Nature']),
        self::CalculateStat('Attack', floor($Base_Stats['Attack'] + $StatBonus), $Level, $IVs[1], $EVs[1], $Pokemon['Nature']),
        self::CalculateStat('Defense', floor($Base_Stats['Defense'] + $StatBonus), $Level, $IVs[2], $EVs[2], $Pokemon['Nature']),
        self::CalculateStat('SpAttack', floor($Base_Stats['SpAttack'] + $StatBonus), $Level, $IVs[3], $EVs[3], $Pokemon['Nature']),
        self::CalculateStat('SpDefense', floor($Base_Stats['SpDefense'] + $StatBonus), $Level, $IVs[4], $EVs[4], $Pokemon['Nature']),
        self::CalculateStat('Speed', floor($Base_Stats['Speed'] + $StatBonus), $Level, $IVs[5], $EVs[5], $Pokemon['Nature']),
      ];

      return $Stats;
    }

    /**
     * Fetches data for a specific move.
     *
     * @param int $Move_ID The ID of the move.
     * @return array|false Associative array of move data or false if not found.
     */
    public function GetMoveData(int $Move_ID)
    {
      try
      {
        $Fetch_Move_Data = $this->pdo->prepare("SELECT * FROM `moves` WHERE `ID` = ? LIMIT 1");
        $Fetch_Move_Data->execute([ $Move_ID ]);
        $Fetch_Move_Data->setFetchMode(PDO::FETCH_ASSOC);
        $Move_Data = $Fetch_Move_Data->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Move_Data )
        return false;

      // Escape data intended for HTML display
      $Move_Data['Name'] = htmlspecialchars($Move_Data['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $Move_Data['Effect_Short'] = htmlspecialchars($Move_Data['Effect_Short'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      // Other fields like Type, Category are usually from a fixed set or used as keys, but escaping wouldn't hurt if displayed.
      // For now, focusing on Name and Description (Effect_Short).

      return [
        "ID" => $Move_Data['ID'], "Name" => $Move_Data['Name'], "Type" => $Move_Data['Move_Type'],
        "Category" => $Move_Data['Category'], "Power" => $Move_Data['Power'], "Accuracy" => $Move_Data['Accuracy'],
        "Priority" => $Move_Data['Priority'], "PP" => $Move_Data['PP'], "Effect_Short" => $Move_Data['Effect_Short'],
      ];
    }

    /**
     * Fetches data for a Pokémon species from the Pokedex.
     *
     * @param int|null $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon. Defaults to 0.
     * @param string $Type The type of the Pokémon (e.g., Normal, Shiny) for sprite purposes. Defaults to "Normal".
     * @return array|false Associative array of Pokedex data or false if not found.
     */
    public function GetPokedexData(?int $Pokedex_ID = null, int $Alt_ID = 0, string $Type = "Normal")
    {
      if ($Pokedex_ID === null) return false; // Ensure Pokedex_ID is provided
      try
      {
        $Get_Pokedex_Data = $this->pdo->prepare("SELECT * FROM `pokedex` WHERE `Pokedex_ID` = ? AND `Alt_ID` = ? LIMIT 1");
        $Get_Pokedex_Data->execute([ $Pokedex_ID, $Alt_ID ]);
        $Get_Pokedex_Data->setFetchMode(PDO::FETCH_ASSOC);
        $Pokedex_Data = $Get_Pokedex_Data->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Pokedex_Data )
        return false;

      $BaseStats = [$Pokedex_Data['HP'], $Pokedex_Data['Attack'], $Pokedex_Data['Defense'], $Pokedex_Data['SpAttack'], $Pokedex_Data['SpDefense'], $Pokedex_Data['Speed']];
      $Type_Display = ($Type != 'Normal') ? $Type : '';
      $Name = $Pokedex_Data['Pokemon'];
      $Display_Name = empty($Pokedex_Data['Forme']) ? $Type_Display . $Pokedex_Data['Pokemon'] : $Type_Display . $Pokedex_Data['Pokemon'] . " " . $Pokedex_Data['Forme'];
      $Poke_Images = $this->GetSprites($Pokedex_Data['Pokedex_ID'], $Pokedex_Data['Alt_ID'], $Type); // GetSprites now returns escaped URLs

      return [
        "ID" => $Pokedex_Data['ID'],
        "Pokedex_ID" => $Pokedex_Data['Pokedex_ID'],
        "Alt_ID" => $Pokedex_Data['Alt_ID'],
        "Name" => htmlspecialchars($Name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        "Forme" => htmlspecialchars($Pokedex_Data['Forme'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        "Display_Name" => htmlspecialchars($Display_Name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        "Type_Primary" => htmlspecialchars($Pokedex_Data['Type_Primary'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        "Type_Secondary" => htmlspecialchars($Pokedex_Data['Type_Secondary'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        "Base_Stats" => $BaseStats, // Array of numbers, no escaping needed
        'Exp_Yield' => $Pokedex_Data['Exp_Yield'], // Number
        'Height' => $Pokedex_Data['Height'], // Number
        'Weight' => $Pokedex_Data['Weight'], // Number
        "Sprite" => $Poke_Images['Sprite'], // Already escaped by GetSprites
        "Icon" => $Poke_Images['Icon'],     // Already escaped by GetSprites
      ];
    }

    /**
     * Fetches detailed information for a specific Pokémon instance.
     *
     * @param int $Pokemon_ID The database ID of the Pokémon.
     * @return array|false An associative array of Pokémon data, or false if not found.
     */
    public function GetPokemonData(int $Pokemon_ID): array|false
    {
      // Validate Pokemon_ID
      if ($Pokemon_ID <= 0) { // Simplified check as it's type-hinted int
          error_log("GetPokemonData: Invalid Pokemon_ID provided: {$Pokemon_ID}");
          return false;
      }
      try
      {
        $Get_Pokemon_Data = $this->pdo->prepare("SELECT * FROM `pokemon` WHERE `ID` = ? LIMIT 1");
        $Get_Pokemon_Data->execute([ $Pokemon_ID ]);
        $Get_Pokemon_Data->setFetchMode(PDO::FETCH_ASSOC);
        $Pokemon_Data = $Get_Pokemon_Data->fetch();

        if (!$Pokemon_Data) return false;

        $Get_Pokemon_Evolution_Count = $this->pdo->prepare("SELECT COUNT(*) FROM `evolution_data` WHERE `poke_id` = ? AND `alt_id` = ? LIMIT 1");
        $Get_Pokemon_Evolution_Count->execute([ $Pokemon_Data['Pokedex_ID'], $Pokemon_Data['Alt_ID'] ]);
        $Can_Evolve_Count = $Get_Pokemon_Evolution_Count->fetchColumn();

        $Item_Data = null;
        if ($Pokemon_Data['Item'] != 0) {
            $Get_Held_Item_Data = $this->pdo->prepare("SELECT `Item_ID`, `Item_Name` FROM `item_dex` WHERE `Item_ID` = ? LIMIT 1");
            $Get_Held_Item_Data->execute([ $Pokemon_Data['Item'] ]);
            $Get_Held_Item_Data->setFetchMode(PDO::FETCH_ASSOC);
            $Item_Data = $Get_Held_Item_Data->fetch();
        }
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      $Pokedex_Data = $this->GetPokedexData($Pokemon_Data['Pokedex_ID'], $Pokemon_Data['Alt_ID']);
      if (!$Pokedex_Data) return false;

      $Gender_Short = '';
      switch($Pokemon_Data['Gender'])
      {
        case 'Female': $Gender_Short = 'F'; break;
        case 'Male': $Gender_Short = 'M'; break;
        case 'Genderless': $Gender_Short = 'G'; break;
        case '?': case '(?)': $Gender_Short = '(?)'; break;
        default: $Gender_Short = "(?)"; break;
      }

      $Stats = $this->GetCurrentStats($Pokemon_ID);
      // Construct Display_Name. Pokedex_Data['Name'] and Pokedex_Data['Forme'] are already escaped.
      // $Pokemon_Data['Type'] is from DB, typically a controlled vocabulary string (Normal, Shiny etc.)
      $Display_Name_Base = htmlspecialchars_decode($Pokedex_Data['Name']); // Use raw name for construction
      $Forme_Base = htmlspecialchars_decode($Pokedex_Data['Forme']);     // Use raw forme for construction
      $Display_Name = ($Pokemon_Data['Type'] !== 'Normal' ? htmlspecialchars($Pokemon_Data['Type'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' ' : '') . $Display_Name_Base;
      if ( !empty($Forme_Base) ) // Check if Forme_Base is not empty after decode
        $Display_Name .= " " . $Forme_Base;
      // Re-escape the fully constructed Display_Name
      $Display_Name_Escaped = htmlspecialchars($Display_Name, ENT_QUOTES | ENT_HTML5, 'UTF-8');


      $Poke_Images = $this->GetSprites($Pokemon_Data['Pokedex_ID'], $Pokemon_Data['Alt_ID'], $Pokemon_Data['Type']); // Already returns escaped URLs

      // Ensure Item_Name used in Item_Icon path is URL-safe or use a dedicated safe filename.
      // For now, assume Item_Name is simple or FetchItemData would provide a safe icon path.
      // If Item_Data exists, its 'Name' should be escaped if displayed directly.
      $Item_Name_Escaped = (!empty($Item_Data) && isset($Item_Data['Item_Name'])) ? htmlspecialchars($Item_Data['Item_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
      $Item_Icon_Path = null;
      if (!empty($Item_Data) && isset($Item_Data['Item_Name'])) {
          // Constructing URL needs care. If Item_Name can have special chars, this could be an issue.
          // However, GetSprites now escapes its output. Item_Icon should follow same pattern or be pre-escaped.
          // For consistency, let's assume Item_Icon path should be escaped.
          $Item_Icon_Path = htmlspecialchars(DOMAIN_SPRITES . '/Items/' . $Item_Data['Item_Name'] . '.png', ENT_QUOTES | ENT_HTML5, 'UTF-8');
      }


      return [
        'ID' => (int)$Pokemon_Data['ID'],
        'Pokedex_ID' => (int)$Pokemon_Data['Pokedex_ID'],
        'Alt_ID' => (int)$Pokemon_Data['Alt_ID'],
        'Nickname' => htmlspecialchars($Pokemon_Data['Nickname'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Display_Name' => $Display_Name_Escaped, // Already escaped
        'Name' => $Pokedex_Data['Name'], // This is already escaped from GetPokedexData
        'Type' => htmlspecialchars($Pokemon_Data['Type'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Location' => htmlspecialchars($Pokemon_Data['Location'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Slot' => (int)$Pokemon_Data['Slot'],
        'Item' => $Item_Name_Escaped, // Item name, escaped
        'Item_ID' => !empty($Item_Data) ? (int)$Item_Data['Item_ID'] : null,
        'Item_Icon' => $Item_Icon_Path, // Item icon path, escaped
        'Gender' => htmlspecialchars($Pokemon_Data['Gender'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Gender_Short' => htmlspecialchars($Gender_Short, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Gender_Icon' => htmlspecialchars(DOMAIN_SPRITES . '/Assets/' . $Pokemon_Data['Gender'] . '.svg', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Level' => number_format(self::FetchLevel((int)$Pokemon_Data['Experience'], 'Pokemon')),
        'Level_Raw' => self::FetchLevel((int)$Pokemon_Data['Experience'], 'Pokemon'),
        'Experience' => number_format($Pokemon_Data['Experience']),
        'Experience_Raw' => (int)$Pokemon_Data['Experience'],
        'Height' => ($Pokedex_Data['Height'] / 10), // Numeric
        'Weight' => ($Pokedex_Data['Weight'] / 10), // Numeric
        'Type_Primary' => $Pokedex_Data['Type_Primary'], // Already escaped from GetPokedexData
        'Type_Secondary' => $Pokedex_Data['Type_Secondary'], // Already escaped from GetPokedexData
        'Ability' => htmlspecialchars($Pokemon_Data['Ability'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Nature' => htmlspecialchars($Pokemon_Data['Nature'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Stats' => $Stats, // Array of numbers
        'IVs' => explode(',', $Pokemon_Data['IVs']), // Array of numbers (strings initially, but usually treated as numbers)
        'EVs' => explode(',', $Pokemon_Data['EVs']), // Array of numbers
        'Move_1' => (int)$Pokemon_Data['Move_1'],
        'Move_2' => (int)$Pokemon_Data['Move_2'],
        'Move_3' => (int)$Pokemon_Data['Move_3'],
        'Move_4' => (int)$Pokemon_Data['Move_4'],
        'Frozen' => (int)$Pokemon_Data['Frozen'],
        'Happiness' => (int)$Pokemon_Data['Happiness'],
        'Exp_Yield' => $Pokedex_Data['Exp_Yield'], // Numeric from GetPokedexData
        'Can_Evolve' => ($Can_Evolve_Count > 0),
        'Owner_Current' => (int)$Pokemon_Data['Owner_Current'],
        'Owner_Original' => (int)$Pokemon_Data['Owner_Original'],
        'Trade_Interest' => htmlspecialchars($Pokemon_Data['Trade_Interest'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Challenge_Status' => htmlspecialchars($Pokemon_Data['Challenge_Status'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Biography' => htmlspecialchars($Pokemon_Data['Biography'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Creation_Date' => htmlspecialchars(date('M j, Y (g:i A)', (int)$Pokemon_Data['Creation_Date']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Creation_Location' => htmlspecialchars($Pokemon_Data['Creation_Location'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Sprite' => $Poke_Images['Sprite'], // Already escaped by GetSprites
        'Icon' => $Poke_Images['Icon'],     // Already escaped by GetSprites
      ];
    }

    /**
     * Generates paths for a Pokémon's sprite and icon.
     * Includes logic to fall back to normal sprites/icons if specific type (e.g., Shiny) is not found.
     *
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon. Defaults to 0.
     * @param string $Type The type of the Pokémon (e.g., Normal, Shiny). Defaults to "Normal".
     * @return array|false Associative array with 'Icon' and 'Sprite' paths, or false on error.
     */
    public function GetSprites(int $Pokedex_ID, int $Alt_ID = 0, string $Type = 'Normal')
    {
      global $Dir_Root; // Used for file_exists checks, consider injecting a base path if possible in future.

      try
      {
        $Get_Pokedex_Data = $this->pdo->prepare("SELECT `Pokedex_ID`, `Alt_ID`, `Forme` FROM `pokedex` WHERE `Pokedex_ID` = ? AND `Alt_ID` = ? LIMIT 1");
        $Get_Pokedex_Data->execute([ $Pokedex_ID, $Alt_ID ]);
        $Get_Pokedex_Data->setFetchMode(PDO::FETCH_ASSOC);
        $Pokedex_Data = $Get_Pokedex_Data->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Pokedex_Data )
        return false;

      $Pokedex_ID_Formatted = str_pad($Pokedex_Data['Pokedex_ID'], 3, "0", STR_PAD_LEFT);
      $Pokemon_Forme_Suffix = '';
      if (!empty($Pokedex_Data['Forme'])) {
        $Pokemon_Forme_Clean = strtolower(preg_replace('/(^\s*\()|(\)\s*$)/', '', $Pokedex_Data['Forme']));
        switch($Pokemon_Forme_Clean)
        {
          case 'mega x': $Pokemon_Forme_Suffix = '-x-mega'; break;
          case 'mega y': $Pokemon_Forme_Suffix = '-y-mega'; break;
          case 'gigantamax': $Pokemon_Forme_Suffix = '-gmax'; break;
          case 'dynamax': $Pokemon_Forme_Suffix = '-dmax'; break;
          case 'female': $Pokemon_Forme_Suffix = '-f'; break;
          case 'male': $Pokemon_Forme_Suffix = '-m'; break;
          default: $Pokemon_Forme_Suffix = "-{$Pokemon_Forme_Clean}"; break;
        }
      }

      $Sprite = DOMAIN_SPRITES . "/Pokemon/Sprites/{$Type}/{$Pokedex_ID_Formatted}{$Pokemon_Forme_Suffix}.png";
      if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Sprite)) )
      {
        $Sprite = DOMAIN_SPRITES . "/Pokemon/Sprites/Normal/{$Pokedex_ID_Formatted}{$Pokemon_Forme_Suffix}.png";
      }

      $Icon = DOMAIN_SPRITES . "/Pokemon/Icons/{$Type}/{$Pokedex_ID_Formatted}{$Pokemon_Forme_Suffix}.png";
      if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Icon)) )
      {
        $Icon = DOMAIN_SPRITES . "/Pokemon/Icons/Normal/{$Pokedex_ID_Formatted}{$Pokemon_Forme_Suffix}.png";
      }

      if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Sprite)) )
      {
        $Sprite = DOMAIN_SPRITES . "/Pokemon/Sprites/{$Type}/{$Pokedex_ID_Formatted}.png";
         if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Sprite)) ) {
            $Sprite = DOMAIN_SPRITES . "/Pokemon/Sprites/Normal/{$Pokedex_ID_Formatted}.png";
         }
      }
       if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Icon)) )
      {
        $Icon = DOMAIN_SPRITES . "/Pokemon/Icons/{$Type}/{$Pokedex_ID_Formatted}.png";
        if (isset($Dir_Root) && !file_exists(str_replace(DOMAIN_SPRITES, $Dir_Root . '/images', $Icon)) ) {
            $Icon = DOMAIN_SPRITES . "/Pokemon/Icons/Normal/{$Pokedex_ID_Formatted}.png";
        }
      }

      return [
        'Icon' => htmlspecialchars($Icon, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'Sprite' => htmlspecialchars($Sprite, ENT_QUOTES | ENT_HTML5, 'UTF-8')
      ];
    }

    /**
     * Randomly generates an ability for a Pokémon based on its possible abilities.
     * Prioritizes Hidden Ability by a 1/50 chance if available.
     *
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon.
     * @return string|false The name of the generated ability, or false if abilities can't be fetched.
     */
    public function GenerateAbility(int $Pokedex_ID, int $Alt_ID)
    {
      $Abilities = $this->GetAbilities($Pokedex_ID, $Alt_ID);
      if ( !$Abilities ) return false;

      // Check for Hidden Ability first (e.g., 1/50 chance)
      if ( !empty($Abilities['Hidden_Ability']) && mt_rand(1, 50) == 1 )
        return $Abilities['Hidden_Ability'];

      // If no Hidden Ability, or didn't roll for it, choose between Ability_1 and Ability_2
      if ( empty($Abilities['Ability_2']) )
        return $Abilities['Ability_1']; // Only Ability_1 exists

      // Randomly pick between Ability_1 and Ability_2
      return (mt_rand(1, 2) == 1) ? $Abilities['Ability_1'] : $Abilities['Ability_2'];
    }

    /**
     * Generates a gender for a Pokémon based on its species' gender ratio.
     *
     * @param int $Pokedex_ID The Pokedex ID of the Pokémon.
     * @param int $Alt_ID The alternate form ID of the Pokémon. Defaults to 0.
     * @return string|false The generated gender ('Female', 'Male', 'Genderless') or false on error.
     */
    public function GenerateGender(int $Pokedex_ID, int $Alt_ID = 0)
    {
      try
      {
        $FetchPokedex = $this->pdo->prepare("SELECT `Female`, `Male`, `Genderless` FROM `pokedex` WHERE `Pokedex_ID` = ? AND `Alt_ID` = ? LIMIT 1");
        $FetchPokedex->execute([ $Pokedex_ID, $Alt_ID ]);
        $FetchPokedex->setFetchMode(PDO::FETCH_ASSOC);
        $Pokemon = $FetchPokedex->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Pokemon ) return false;

      $Weighter = new Weighter();
      foreach ( ['Female', 'Male', 'Genderless'] as $Gender )
      {
        if (isset($Pokemon[$Gender]))
          $Weighter->AddObject($Gender, $Pokemon[$Gender]);
      }

      return $Weighter->GetObject();
    }

    /**
     * Moves a Pokémon to a different slot or location (Roster/Box).
     *
     * @param int $Pokemon_ID The ID of the Pokémon to move.
     * @param int $User_ID The ID of the user owning the Pokémon.
     * @param int $Slot The target slot (1-6 for Roster, 7 for Box default). Defaults to 7.
     * @return array An associative array with 'Message' and 'Type' (success/error).
     */
    public function MovePokemon(int $Pokemon_ID, int $User_ID, int $Slot = 7)
    {
      try
      {
        $Get_Selected_Pokemon = $this->pdo->prepare("SELECT `ID`, `Owner_Current`, `Location`, `Slot` FROM `pokemon` WHERE `ID` = ? LIMIT 1");
        $Get_Selected_Pokemon->execute([ $Pokemon_ID ]);
        $Selected_Pokemon = $Get_Selected_Pokemon->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return ['Message' => 'Error fetching Pokemon data.', 'Type' => 'error'];
      }

      if ( !$Selected_Pokemon ) return ['Message' => 'This Pok&eacute;mon does not exist.', 'Type' => 'error'];
      if ( $Selected_Pokemon['Owner_Current'] != $User_ID ) return ['Message' => 'This Pok&eacute;mon does not belong to you.', 'Type' => 'error'];
      if ( !in_array($Slot, [1, 2, 3, 4, 5, 6, 7]) ) $Slot = 7;

      try
      {
        $Get_User_Roster = $this->pdo->prepare("SELECT `ID`, `Slot` FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Roster' AND `Slot` <= 6 ORDER BY `Slot` ASC LIMIT 6");
        $Get_User_Roster->execute([ $User_ID ]);
        $User_Roster_Raw = $Get_User_Roster->fetchAll(PDO::FETCH_ASSOC);
        $User_Roster = [];
        foreach ($User_Roster_Raw as $Roster_Pokemon) {
            $User_Roster[$Roster_Pokemon['Slot']] = $Roster_Pokemon['ID'];
        }

      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return ['Message' => 'Error fetching roster data.', 'Type' => 'error'];
      }

      $Poke_Data = $this->GetPokemonData($Pokemon_ID);
      if (!$Poke_Data) return ['Message' => 'Could not fetch details for the Pokemon being moved.', 'Type' => 'error'];


      if ( $Slot == 7 )
      {
        try
        {
          $this->pdo->beginTransaction();
          $Update_Pokemon = $this->pdo->prepare("UPDATE `pokemon` SET `Location` = 'Box', `Slot` = ? WHERE `ID` = ? LIMIT 1");
          $Update_Pokemon->execute([ $Slot, $Poke_Data['ID'] ]);
          $this->pdo->commit();
        }
        catch ( PDOException $e ) { $this->pdo->rollBack(); HandleError($e); return ['Message' => 'Error moving Pokemon to box.', 'Type' => 'error']; }
        $Move_Message = "<b>{$Poke_Data['Display_Name']}</b> has been sent to your box.";
      }
      else
      {
        try
        {
          $this->pdo->beginTransaction();
          $Swapped_Pokemon_ID = $User_Roster[$Slot] ?? null;

          if ($Swapped_Pokemon_ID) {
              $Update_Swapped = $this->pdo->prepare("UPDATE `pokemon` SET `Location` = ?, `Slot` = ? WHERE `ID` = ? LIMIT 1");
              $Update_Swapped->execute([$Selected_Pokemon['Location'], $Selected_Pokemon['Slot'], $Swapped_Pokemon_ID]);
          }

          $Update_Selected = $this->pdo->prepare("UPDATE `pokemon` SET `Location` = 'Roster', `Slot` = ? WHERE `ID` = ? LIMIT 1");
          $Update_Selected->execute([$Slot, $Poke_Data['ID']]);

          $this->pdo->commit();
        }
        catch ( PDOException $e ) { $this->pdo->rollBack(); HandleError($e); return ['Message' => 'Error moving Pokemon to roster.', 'Type' => 'error'];}
        $Move_Message = "<b>{$Poke_Data['Display_Name']}</b> has been added to your roster.";
      }
      return ['Message' => $Move_Message, 'Type' => 'success'];
    }

    /**
     * Releases a Pokémon, moving its data to the 'released' table and deleting it from 'pokemon'.
     *
     * @param int $Pokemon_ID The ID of the Pokémon to release.
     * @param int $User_ID The ID of the user releasing the Pokémon.
     * @param bool $Staff_Panel_Deletion If true, bypasses ownership check (for staff actions). Defaults to false.
     * @return array An associative array with 'Type' (success/error) and 'Message'.
     */
    public function ReleasePokemon(int $Pokemon_ID, int $User_ID, bool $Staff_Panel_Deletion = false)
    {
      $Pokemon = $this->GetPokemonData($Pokemon_ID);

      if ( !$Pokemon )
        return ['Type' => 'error', 'Message' => 'This Pok&eacute;mon does not exist.'];

      // Ownership check, bypassed if staff is deleting
      if ( $Pokemon['Owner_Current'] != $User_ID && !$Staff_Panel_Deletion)
        return ['Type' => 'error', 'Message' => 'You may not release a Pok&eacute;mon that does not belong to you.'];

      try
      {
        $this->pdo->beginTransaction();
        $Release_Pokemon = $this->pdo->prepare("
          INSERT INTO `released` ( ID, Pokedex_ID, Alt_ID, Name, Forme, Type, Location, Slot, Item, Owner_Current, Owner_Original, Gender, Experience, IVs, EVs, Nature, Happiness, Trade_Interest, Challenge_Status, Frozen, Ability, Move_1, Move_2, Move_3, Move_4, Nickname, Biography, Creation_Date, Creation_Location )
          SELECT ID, Pokedex_ID, Alt_ID, Name, Forme, Type, Location, Slot, Item, Owner_Current, Owner_Original, Gender, Experience, IVs, EVs, Nature, Happiness, Trade_Interest, Challenge_Status, Frozen, Ability, Move_1, Move_2, Move_3, Move_4, Nickname, Biography, Creation_Date, Creation_Location
          FROM `pokemon` WHERE ID = ?;
        ");
        $Release_Pokemon->execute([ $Pokemon_ID ]);
        $Delete_Pokemon = $this->pdo->prepare("DELETE FROM `pokemon` WHERE ID = ?;");
        $Delete_Pokemon->execute([ $Pokemon_ID ]);
        $this->pdo->commit();
      }
      catch ( PDOException $e ) { $this->pdo->rollBack(); HandleError($e); return ['Type' => 'error', 'Message' => 'Database error during release.']; }

      return ['Type' => 'success', 'Message' => "You have successfully released {$Pokemon['Display_Name']}."];
    }

    /**
     * Fetches basic information for all Pokémon a user can release (in Box, not frozen).
     *
     * @param int $UserID The ID of the user.
     * @return array An associative array with 'Amount' and 'Pokemon' list (ID, Display_Name, Gender, Level_Raw).
     */
    public function GetUserReleasablePokemonIDsAndBasicInfo(int $UserID)
    {
      $Releasable_Pokemon_With_Info = [];
      try
      {
        $Get_Releasable_Pokemon = $this->pdo->prepare("
          SELECT `ID`
          FROM `pokemon`
          WHERE `Owner_Current` = ? AND `Location` = 'Box' AND `Frozen` = 0
        ");
        $Get_Releasable_Pokemon->execute([ $UserID ]);
        $Releasable_Pokemon_IDs = $Get_Releasable_Pokemon->fetchAll(PDO::FETCH_ASSOC);

        if ( !empty($Releasable_Pokemon_IDs) )
        {
          foreach ( $Releasable_Pokemon_IDs as $Pokemon_ID_Row )
          {
            $Pokemon_Info = $this->GetPokemonData($Pokemon_ID_Row['ID']);
            if ($Pokemon_Info) {
              $Releasable_Pokemon_With_Info[] = [
                'ID' => $Pokemon_Info['ID'],
                'Display_Name' => $Pokemon_Info['Display_Name'],
                'Gender' => $Pokemon_Info['Gender'],
                'Level' => $Pokemon_Info['Level_Raw']
              ];
            }
          }
        }
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return ['Amount' => 0, 'Pokemon' => []];
      }

      return [
        'Amount' => count($Releasable_Pokemon_With_Info),
        'Pokemon' => $Releasable_Pokemon_With_Info
      ];
    }

    /**
     * Checks if a specific Pokémon is owned by a specific user.
     *
     * @param int $PokemonID The ID of the Pokémon.
     * @param int $UserID The ID of the user.
     * @return bool True if the user owns the Pokémon, false otherwise.
     */
    public function CheckPokemonOwnership(int $PokemonID, int $UserID)
    {
      try
      {
        $Check_Pokemon_Ownership = $this->pdo->prepare("
          SELECT `ID`
          FROM `pokemon`
          WHERE `ID` = ? AND `Owner_Current` = ?
          LIMIT 1
        ");
        $Check_Pokemon_Ownership->execute([ $PokemonID, $UserID ]);
        return $Check_Pokemon_Ownership->fetch() !== false;
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Fetches all moves marked as 'Usable'.
     *
     * @return array|false An array of usable moves (ID, Name) or false on error.
     */
    public function GetAllUsableMoves()
    {
      try
      {
        $Get_Moves = $this->pdo->prepare("SELECT `ID`, `Name` FROM `moves` WHERE `Usable` = 1 ORDER BY `Name` ASC");
        $Get_Moves->execute([]);
        $Get_Moves->setFetchMode(PDO::FETCH_ASSOC);
        return $Get_Moves->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Updates a specific move slot for a Pokémon.
     * Prevents duplicate moves unless one of them is 0 (empty move).
     *
     * @param int $PokemonID The ID of the Pokémon.
     * @param int $UserID The ID of the user owning the Pokémon.
     * @param int $MoveSlotNumber The move slot to update (1-4).
     * @param int $NewMoveID The ID of the new move to learn (0 to forget).
     * @return array An associative array with 'Success' (bool) and 'Message' (string).
     */
    public function UpdatePokemonMoveSlot(int $PokemonID, int $UserID, int $MoveSlotNumber, int $NewMoveID)
    {
      if ( !in_array($MoveSlotNumber, [1, 2, 3, 4], true) ) { // Strict check for in_array
        return ['Success' => false, 'Message' => 'Invalid move slot.'];
      }

      try
      {
        $this->pdo->beginTransaction();

        $Fetch_Pokemon = $this->pdo->prepare("SELECT `ID`, `Move_1`, `Move_2`, `Move_3`, `Move_4` FROM `pokemon` WHERE `ID` = ? AND `Owner_Current` = ? LIMIT 1");
        $Fetch_Pokemon->execute([ $PokemonID, $UserID ]);
        $Pokemon_Current_Moves = $Fetch_Pokemon->fetch(PDO::FETCH_ASSOC);

        if ( !$Pokemon_Current_Moves )
        {
          $this->pdo->rollBack();
          return ['Success' => false, 'Message' => 'This Pok&eacute;mon does not belong to you or does not exist.'];
        }

        if ( $NewMoveID != 0 )
        {
          $Current_Moves_Array = [
            $Pokemon_Current_Moves['Move_1'],
            $Pokemon_Current_Moves['Move_2'],
            $Pokemon_Current_Moves['Move_3'],
            $Pokemon_Current_Moves['Move_4']
          ];

          $Temp_Moves_Array = $Current_Moves_Array;
          $Temp_Moves_Array[$MoveSlotNumber - 1] = $NewMoveID;

          $Filtered_Moves_Array = array_filter($Temp_Moves_Array, function($value) { return $value != 0; });

          if ( count($Filtered_Moves_Array) !== count(array_unique($Filtered_Moves_Array)) )
          {
            $this->pdo->rollBack();
            return ['Success' => false, 'Message' => 'Pok&eacute;mon may not have multiple copies of the same move.'];
          }
        }

        $Column_Name = "Move_{$MoveSlotNumber}";
        $Update_Move = $this->pdo->prepare("UPDATE `pokemon` SET `{$Column_Name}` = ? WHERE `ID` = ?");
        $Update_Move->execute([ $NewMoveID, $PokemonID ]);

        $this->pdo->commit();
        return ['Success' => true, 'Message' => 'You have updated this Pok&eacute;mon\'s moves.'];
      }
      catch ( PDOException $e )
      {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        HandleError($e);
        return ['Success' => false, 'Message' => 'An error occurred while updating moves.'];
      }
    }

    /**
     * Updates the nickname of a Pokémon.
     *
     * @param int $PokemonID The ID of the Pokémon.
     * @param int $UserID The ID of the user owning the Pokémon.
     * @param string|null $Nickname The new nickname. If null or empty, the nickname is removed.
     * @return array An associative array with 'Success' (bool) and 'Message' (string).
     */
    public function UpdatePokemonNickname(int $PokemonID, int $UserID, ?string $Nickname)
    {
      // Explicitly check ownership first.
      $Is_Owner = $this->CheckPokemonOwnership($PokemonID, $UserID);
      if (!$Is_Owner)
      {
        return [
          'Success' => false,
          'Message' => 'You may not update the nickname of a Pok&eacute;mon that is not yours or does not exist.'
        ];
      }

      // Fetch minimal data for the message, or use a placeholder if GetPokemonData is too heavy here.
      // For now, let's fetch it to get an accurate display name.
      $PokemonForMessage = $this->GetPokemonData($PokemonID);

      // Sanitize the nickname. Purify allows for some characters but strips dangerous ones.
      // Max length should be enforced by DB schema or here.
      $Nickname_To_Set = (empty($Nickname) || trim($Nickname) === '') ? null : Purify(trim($Nickname));

      try
      {
        $this->pdo->beginTransaction();
        $Update_Pokemon = $this->pdo->prepare("UPDATE `pokemon` SET `Nickname` = ? WHERE `ID` = ? AND `Owner_Current` = ?");
        $Update_Pokemon->execute([ $Nickname_To_Set, $PokemonID, $UserID ]);

        if ($Update_Pokemon->rowCount() == 0) {
            $this->pdo->rollBack();
             return [
              'Success' => false,
              'Message' => 'Could not update nickname (Pokemon not found or not owned).'
            ];
        }
        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return [
          'Success' => false,
          'Message' => 'An error occurred while updating the nickname.'
        ];
      }

      $pokemonDisplayName = $PokemonForMessage ? htmlspecialchars($PokemonForMessage['Display_Name'], ENT_QUOTES, 'UTF-8') : 'The Pok&eacute;mon';

      if ($Nickname_To_Set) {
        return [
          'Success' => true,
          'Message' => "<b>{$pokemonDisplayName}</b>'s new nickname is <b>" . htmlspecialchars($Nickname_To_Set, ENT_QUOTES, 'UTF-8') . "</b>."
        ];
      } else {
        return [
          'Success' => true,
          'Message' => "<b>{$pokemonDisplayName}</b>'s nickname has been removed."
        ];
      }
    }


    /**
     * Calculates a Pokémon's stat based on its base value, level, IV, EV, and nature.
     * This is a static helper method.
     *
     * @param string $Stat_Name The name of the stat (e.g., 'HP', 'Attack').
     * @param int $Base_Stat_Value The base stat value for the species.
     * @param int $Pokemon_Level The Pokémon's current level.
     * @param int $Pokemon_Stat_IV The Pokémon's IV for this stat.
     * @param int $Pokemon_Stat_EV The Pokémon's EV for this stat.
     * @param string $Pokemon_Nature The Pokémon's nature.
     * @return int The calculated stat value.
     */
    public static function CalculateStat(string $Stat_Name, int $Base_Stat_Value, int $Pokemon_Level, int $Pokemon_Stat_IV, int $Pokemon_Stat_EV, string $Pokemon_Nature): int
    {
      // Basic validation/clamping
      $Pokemon_Level = max(1, $Pokemon_Level);
      $Pokemon_Stat_IV = max(0, min(31, $Pokemon_Stat_IV));
      $Pokemon_Stat_EV = max(0, min(252, $Pokemon_Stat_EV));

      if ( $Stat_Name == 'HP' ) {
        if ( $Base_Stat_Value == 1 ) return 1; // Shedinja case
        return floor((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + $Pokemon_Level + 10);
      } else {
        $Nature_Data = self::Natures()[$Pokemon_Nature] ?? ['Plus' => null, 'Minus' => null]; // Default if nature not found
        $Nature_Bonus = 1.0;
        if (isset($Nature_Data['Plus']) && $Nature_Data['Plus'] == $Stat_Name ) $Nature_Bonus = 1.1;
        else if (isset($Nature_Data['Minus']) && $Nature_Data['Minus'] == $Stat_Name ) $Nature_Bonus = 0.9;

        return floor(((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + 5) * $Nature_Bonus);
      }
    }

    /**
     * Returns an array of natures and their stat modifications.
     * This is a static helper method.
     *
     * @return array Associative array of natures.
     */
    public static function Natures(): array
    {
      return [
        'Adamant' => ['Plus' => 'Attack', 'Minus' => 'SpAttack'], 'Brave' => ['Plus' => 'Attack', 'Minus' => 'Speed'],
        'Lonely' => ['Plus' => 'Attack', 'Minus' => 'Defense'], 'Naughty' => ['Plus' => 'Attack', 'Minus' => 'SpDefense'],
        'Bold' => ['Plus' => 'Defense', 'Minus' => 'Attack'], 'Impish' => ['Plus' => 'Defense', 'Minus' => 'SpAttack'],
        'Lax' => ['Plus' => 'Defense', 'Minus' => 'SpDefense'], 'Relaxed' => ['Plus' => 'Defense', 'Minus' => 'Speed'],
        'Modest' => ['Plus' => 'SpAttack', 'Minus' => 'Attack'], 'Mild' => ['Plus' => 'SpAttack', 'Minus' => 'Defense'],
        'Quiet' => ['Plus' => 'SpAttack', 'Minus' => 'Speed'], 'Rash' => ['Plus' => 'SpAttack', 'Minus' => 'SpDefense'],
        'Calm' => ['Plus' => 'SpDefense', 'Minus' => 'Attack'], 'Careful' => ['Plus' => 'SpDefense', 'Minus' => 'SpAttack'],
        'Gentle' => ['Plus' => 'SpDefense', 'Minus' => 'Defense'], 'Sassy' => ['Plus' => 'SpDefense', 'Minus' => 'Speed'],
        'Hasty' => ['Plus' => 'Speed', 'Minus' => 'Defense'], 'Jolly' => ['Plus' => 'Speed', 'Minus' => 'SpAttack'],
        'Naive' => ['Plus' => 'Speed', 'Minus' => 'SpDefense'], 'Timid' => ['Plus' => 'Speed', 'Minus' => 'Attack'],
        'Bashful' => ['Plus' => null, 'Minus' => null], 'Docile' => ['Plus' => null, 'Minus' => null],
        'Hardy' => ['Plus' => null, 'Minus' => null], 'Quirky' => ['Plus' => null, 'Minus' => null],
        'Serious' => ['Plus' => null, 'Minus' => null],
      ];
    }

    /**
     * Generates a random nature.
     * This is a static helper method.
     *
     * @return string A random nature name.
     */
    public static function GenerateNature(): string
    {
      $Nature_Keys = array_keys(self::Natures());
      return $Nature_Keys[mt_rand(0, count($Nature_Keys) - 1)];
    }

    /**
     * Fetches the level for a given experience amount and type (Pokemon/Trainer).
     * Relies on global $Pokemon_Exp_Needed.
     * This is a static helper method.
     *
     * @param int $Current_Exp The current experience points.
     * @param string $Type The type of entity ('Pokemon' or 'Trainer').
     * @return int The calculated level.
     */
    public static function FetchLevel(int $Current_Exp, string $Type): int
    {
      global $Pokemon_Exp_Needed;
      if (isset($Pokemon_Exp_Needed[$Type])) {
        foreach ($Pokemon_Exp_Needed[$Type] as $Lvl => $Exp) {
          if ( $Current_Exp >= $Exp ) {
            continue;
          } else {
            return $Lvl - 1;
          }
        }
      }
      return 1; // Default to level 1 if type or exp not found
    }

    /**
     * Fetches the experience needed for a specific level and type.
     * Relies on global $Pokemon_Exp_Needed.
     * This is a static helper method.
     *
     * @param int $Level The desired level.
     * @param string $Type The type of entity ('Pokemon' or 'Trainer').
     * @return int The experience points needed for that level.
     */
    public static function FetchExperience(int $Level, string $Type): int
    {
      global $Pokemon_Exp_Needed;
      if (isset($Pokemon_Exp_Needed[$Type][$Level])) {
        return $Pokemon_Exp_Needed[$Type][$Level];
      }
      return 1; // Default if not found
    }

    /**
     * Heals all Pokémon in a user's roster to full HP and removes status conditions.
     *
     * @param int $UserID The ID of the user whose roster Pokémon are to be healed.
     * @return array An associative array with 'Success' (bool) and 'Message' (string).
     */
    public function HealRosterPokemon(int $UserID): array
    {
      try
      {
        $this->pdo->beginTransaction();

        // Fetch roster Pokemon IDs
        $Fetch_Roster_IDs = $this->pdo->prepare("SELECT `ID` FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Roster'");
        $Fetch_Roster_IDs->execute([$UserID]);
        $Roster_Pokemon_IDs = $Fetch_Roster_IDs->fetchAll(PDO::FETCH_COLUMN);

        if (empty($Roster_Pokemon_IDs)) {
          $this->pdo->commit(); // Nothing to heal
          return ['Success' => true, 'Message' => 'No Pok&eacute;mon in roster to heal.'];
        }

        // Heal each Pokemon
        foreach ($Roster_Pokemon_IDs as $Pokemon_ID) {
          $Current_Stats = $this->GetCurrentStats((int)$Pokemon_ID);
          if ($Current_Stats && isset($Current_Stats[0])) { // Index 0 is HP
            $Max_HP = $Current_Stats[0];

            // Update HP and clear status conditions
            // Assuming common status column names. Adjust if schema is different.
            $Update_Pokemon = $this->pdo->prepare("
              UPDATE `pokemon`
              SET
                `HP_Current` = ?,
                `Status_Effect` = NULL,
                `Status_Sleep_Turns` = 0,
                `Status_Toxic_Turns` = 0
                /* Add other status columns here if they exist, like `Status_Burn_Turns`, `Status_Freeze_Turns`, `Status_Paralysis_Turns` */
              WHERE `ID` = ?
            ");
            $Update_Pokemon->execute([$Max_HP, $Pokemon_ID]);
          }
        }

        $this->pdo->commit();
        return ['Success' => true, 'Message' => 'All Pok&eacute;mon in your roster have been healed!'];
      }
      catch (PDOException $e)
      {
        if ($this->pdo->inTransaction()) {
          $this->pdo->rollBack();
        }
        HandleError($e);
        return ['Success' => false, 'Message' => 'An error occurred while healing your Pok&eacute;mon.'];
      }
    }

    /**
     * Fetches a paginated list of Pokémon from a user's PC Box.
     *
     * @param int $UserID The ID of the user.
     * @param int $Page The current page number for pagination. Defaults to 1.
     * @param int $Items_Per_Page The number of items to display per page. Defaults to 30.
     * @return array An associative array containing the list of Pokémon, total count, and pagination details.
     *               Includes an 'error' key if a database error occurs.
     */
    public function FetchBoxPokemon(int $UserID, int $Page = 1, int $Items_Per_Page = 30): array
    {
      // Sanitize pagination parameters
      $Page = max(1, $Page);
      $Items_Per_Page = max(1, $Items_Per_Page);
      $Offset = ($Page - 1) * $Items_Per_Page;

      $pokemon_list = [];
      $total_pokemon = 0;

      try {
        // Get total count of Pokémon in the user's box
        $Count_Query = $this->pdo->prepare("SELECT COUNT(*) FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Box'");
        $Count_Query->execute([$UserID]);
        $total_pokemon = (int)$Count_Query->fetchColumn();

        if ($total_pokemon > 0) {
          // Fetch paginated Pokemon IDs from the box
          $Fetch_IDs = $this->pdo->prepare("
            SELECT `ID`
            FROM `pokemon`
            WHERE `Owner_Current` = ? AND `Location` = 'Box'
            ORDER BY `ID` ASC  -- Or some other meaningful order like Pokedex_ID, Slot, etc.
            LIMIT :limit OFFSET :offset
          ");
          $Fetch_IDs->bindValue(':limit', $Items_Per_Page, PDO::PARAM_INT);
          $Fetch_IDs->bindValue(':offset', $Offset, PDO::PARAM_INT);
          $Fetch_IDs->bindValue(1, $UserID, PDO::PARAM_INT); // Assuming unnamed placeholder for UserID from original query structure
          $Fetch_IDs->execute();
          $Pokemon_IDs = $Fetch_IDs->fetchAll(PDO::FETCH_COLUMN);

          // Get full data for each fetched Pokémon ID
          foreach ($Pokemon_IDs as $pokemon_id) {
            $pokemon_data = $this->GetPokemonData((int)$pokemon_id);
            if ($pokemon_data) {
              $pokemon_list[] = $pokemon_data;
            }
          }
        }
      } catch (PDOException $e) {
        HandleError($e);
        // Return error state
        return [
          'pokemon' => [],
          'total_pokemon' => 0,
          'current_page' => $Page,
          'items_per_page' => $Items_Per_Page,
          'error' => 'A database error occurred while fetching box Pokémon.'
        ];
      }

      return [
        'pokemon' => $pokemon_list,
        'total_pokemon' => $total_pokemon,
        'current_page' => $Page,
        'items_per_page' => $Items_Per_Page,
      ];
    }

    /**
     * Fetches the user's roster Pokémon along with detailed information about their current moves.
     * Ensures that data intended for display (like Pokémon names, move names) is HTML-escaped.
     *
     * @param int $UserID The ID of the user whose roster to fetch.
     * @return array An array of Pokémon data, where each Pokémon includes a 'Move_Data' key
     *               containing details for its learned moves. Returns empty array on failure or no roster.
     */
    public function GetUserRosterWithDetailedMoves(int $UserID): array
    {
      if ($UserID <= 0) {
        return [];
      }

      $roster_pokemon_data = [];
      try {
        $stmt = $this->pdo->prepare("
          SELECT `ID`, `Move_1`, `Move_2`, `Move_3`, `Move_4`
          FROM `pokemon`
          WHERE `Owner_Current` = ? AND `Location` = 'Roster'
          ORDER BY `Slot` ASC
          LIMIT 6
        ");
        $stmt->execute([$UserID]);
        $roster_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($roster_slots as $roster_slot) {
          $pokemon_detail = $this->GetPokemonData((int)$roster_slot['ID']);
          if (!$pokemon_detail) {
            // Placeholder for empty/invalid slot if needed by JS, or skip
            $roster_pokemon_data[] = null; // Or some default structure for an empty slot
            continue;
          }

          $pokemon_detail['Move_Data'] = [];
          $move_ids = [
            1 => $roster_slot['Move_1'],
            2 => $roster_slot['Move_2'],
            3 => $roster_slot['Move_3'],
            4 => $roster_slot['Move_4'],
          ];

          foreach ($move_ids as $slot_num => $move_id) {
            if ($move_id > 0) {
              $move_data = $this->GetMoveData((int)$move_id);
              $pokemon_detail['Move_Data'][(string)$slot_num] = $move_data ? $move_data : ['Name' => 'Empty', 'ID' => 0]; // GetMoveData now escapes Name
            } else {
              $pokemon_detail['Move_Data'][(string)$slot_num] = ['Name' => 'Empty', 'ID' => 0];
            }
          }
          $roster_pokemon_data[] = $pokemon_detail;
        }
      } catch (PDOException $e) {
        HandleError($e);
        return []; // Return empty array on error
      }

      return $roster_pokemon_data;
    }
  }

[end of app/core/classes/pokemon_service.php]
