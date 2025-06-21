<?php
  class PokemonService
  {
    private $pdo;

    public function __construct(PDO $pdo)
    {
      $this->pdo = $pdo;
    }

    public function CreatePokemon(
      $Owner_ID,
      $Pokedex_ID,
      $Alt_ID,
      $Level = 5,
      $Type = "Normal",
      $Gender = null,
      $Obtained_At = "Unknown",
      $Nature = null,
      $IVs = null,
      $EVs = null
    )
    {
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

    public function GetAbilities($Pokedex_ID, $Alt_ID)
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

    public function GetBaseStats($Pokedex_ID, $Alt_ID)
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

    public function GetMoveData($Move_ID)
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

      return [
        "ID" => $Move_Data['ID'], "Name" => $Move_Data['Name'], "Type" => $Move_Data['Move_Type'],
        "Category" => $Move_Data['Category'], "Power" => $Move_Data['Power'], "Accuracy" => $Move_Data['Accuracy'],
        "Priority" => $Move_Data['Priority'], "PP" => $Move_Data['PP'], "Effect_Short" => $Move_Data['Effect_Short'],
      ];
    }

    public function GetPokedexData($Pokedex_ID = null, $Alt_ID = 0, $Type = "Normal")
    {
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
      $Poke_Images = $this->GetSprites($Pokedex_Data['Pokedex_ID'], $Pokedex_Data['Alt_ID'], $Type);

      return [
        "ID" => $Pokedex_Data['ID'], "Pokedex_ID" => $Pokedex_Data['Pokedex_ID'], "Alt_ID" => $Pokedex_Data['Alt_ID'],
        "Name" => $Name, "Forme" => $Pokedex_Data['Forme'], "Display_Name" => $Display_Name,
        "Type_Primary" => $Pokedex_Data['Type_Primary'], "Type_Secondary" => $Pokedex_Data['Type_Secondary'],
        "Base_Stats" => $BaseStats, 'Exp_Yield' => $Pokedex_Data['Exp_Yield'], 'Height' => $Pokedex_Data['Height'], 'Weight' => $Pokedex_Data['Weight'],
        "Sprite" => $Poke_Images['Sprite'], "Icon" => $Poke_Images['Icon'],
      ];
    }

    public function GetPokemonData($Pokemon_ID)
    {
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
      $Display_Name = ($Pokemon_Data['Type'] !== 'Normal' ? $Pokemon_Data['Type'] : '') . $Pokemon_Data['Name'];
      if ( $Pokemon_Data['Forme'] )
        $Display_Name .= " {$Pokemon_Data['Forme']}";

      $Poke_Images = $this->GetSprites($Pokemon_Data['Pokedex_ID'], $Pokemon_Data['Alt_ID'], $Pokemon_Data['Type']);

      return [
        'ID' => $Pokemon_Data['ID'], 'Pokedex_ID' => $Pokemon_Data['Pokedex_ID'], 'Alt_ID' => $Pokemon_Data['Alt_ID'],
        'Nickname' => $Pokemon_Data['Nickname'], 'Display_Name' => $Display_Name, 'Name' => $Pokemon_Data['Name'],
        'Type' => $Pokemon_Data['Type'], 'Location' => $Pokemon_Data['Location'], 'Slot' => $Pokemon_Data['Slot'],
        'Item' => (!empty($Item_Data) ? $Item_Data['Item_Name'] : null), 'Item_ID' => (!empty($Item_Data) ? $Item_Data['Item_ID'] : null),
        'Item_Icon' => (!empty($Item_Data) ? DOMAIN_SPRITES . '/Items/' . $Item_Data['Item_Name'] . '.png' : null),
        'Gender' => $Pokemon_Data['Gender'], 'Gender_Short' => $Gender_Short, 'Gender_Icon' => DOMAIN_SPRITES . '/Assets/' . $Pokemon_Data['Gender'] . '.svg',
        'Level' => number_format(self::FetchLevel($Pokemon_Data['Experience'], 'Pokemon')), 'Level_Raw' => self::FetchLevel($Pokemon_Data['Experience'], 'Pokemon'),
        'Experience' => number_format($Pokemon_Data['Experience']), 'Experience_Raw' => $Pokemon_Data['Experience'],
        'Height' => ($Pokedex_Data['Height'] / 10), 'Weight' => ($Pokedex_Data['Weight'] / 10),
        'Type_Primary' => $Pokedex_Data['Type_Primary'], 'Type_Secondary' => $Pokedex_Data['Type_Secondary'],
        'Ability' => $Pokemon_Data['Ability'], 'Nature' => $Pokemon_Data['Nature'],
        'Stats' => $Stats, 'IVs' => explode(',', $Pokemon_Data['IVs']), 'EVs' => explode(',', $Pokemon_Data['EVs']),
        'Move_1' => $Pokemon_Data['Move_1'], 'Move_2' => $Pokemon_Data['Move_2'], 'Move_3' => $Pokemon_Data['Move_3'], 'Move_4' => $Pokemon_Data['Move_4'],
        'Frozen' => $Pokemon_Data['Frozen'], 'Happiness' => $Pokemon_Data['Happiness'], 'Exp_Yield' => $Pokedex_Data['Exp_Yield'],
        'Can_Evolve' => ($Can_Evolve_Count > 0),
        'Owner_Current' => $Pokemon_Data['Owner_Current'], 'Owner_Original' => $Pokemon_Data['Owner_Original'],
        'Trade_Interest' => $Pokemon_Data['Trade_Interest'], 'Challenge_Status' => $Pokemon_Data['Challenge_Status'],
        'Biography' => $Pokemon_Data['Biography'], 'Creation_Date' => date('M j, Y (g:i A)', $Pokemon_Data['Creation_Date']),
        'Creation_Location' => $Pokemon_Data['Creation_Location'],
        'Sprite' => $Poke_Images['Sprite'], 'Icon' => $Poke_Images['Icon'],
      ];
    }

    public function GetSprites($Pokedex_ID, $Alt_ID = 0, $Type = 'Normal')
    {
      global $Dir_Root;

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

      return [ 'Icon' => $Icon, 'Sprite' => $Sprite ];
    }

    public function GenerateAbility($Pokedex_ID, $Alt_ID)
    {
      $Abilities = $this->GetAbilities($Pokedex_ID, $Alt_ID);
      if ( !$Abilities ) return false;
      if ( !empty($Abilities['Hidden_Ability']) && mt_rand(1, 50) == 1 ) return $Abilities['Hidden_Ability'];
      if ( empty($Abilities['Ability_2']) ) return $Abilities['Ability_1'];
      return (mt_rand(1, 2) == 1) ? $Abilities['Ability_1'] : $Abilities['Ability_2'];
    }

    public function GenerateGender($Pokedex_ID, $Alt_ID = 0)
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

    public function MovePokemon($Pokemon_ID, $User_ID, $Slot = 7)
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

    public function ReleasePokemon($Pokemon_ID, $User_ID, $Staff_Panel_Deletion = false)
    {
      $Pokemon = $this->GetPokemonData($Pokemon_ID);

      if ( !$Pokemon ) return ['Type' => 'error', 'Message' => 'This Pok&eacute;mon does not exist.'];
      if ( $Pokemon['Owner_Current'] != $User_ID && !$Staff_Panel_Deletion) return ['Type' => 'error', 'Message' => 'You may not release a Pok&eacute;mon that does not belong to you.'];

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

    public function UpdatePokemonMoveSlot(int $PokemonID, int $UserID, int $MoveSlotNumber, int $NewMoveID)
    {
      if ( !in_array($MoveSlotNumber, [1, 2, 3, 4]) ) {
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

    public function UpdatePokemonNickname(int $PokemonID, int $UserID, ?string $Nickname)
    {
      $Pokemon = $this->GetPokemonData($PokemonID); // This already checks ownership via its own logic if GetPokemonData is called with UserID context or if it implicitly uses a session UserID.
                                                  // However, the current GetPokemonData doesn't take UserID for an ownership check.
                                                  // It's better to explicitly check ownership first.

      $Is_Owner = $this->CheckPokemonOwnership($PokemonID, $UserID);
      if (!$Is_Owner)
      {
        return [
          'Success' => false,
          'Message' => 'You may not update the nickname of a Pok&eacute;mon that is not yours or does not exist.'
        ];
      }

      // Re-fetch Pokemon data if needed, or use data from CheckPokemonOwnership if it returned the row.
      // For simplicity, let's assume $Pokemon (if fetched after ownership check) is valid.
      // If $Pokemon was from before ownership check, it might be for a different user's pokemon if ID is reused.
      // Safest is to re-fetch or ensure CheckPokemonOwnership returns the data.
      // For now, we'll rely on the earlier GetPokemonData and the subsequent ownership check.
      // This means $Pokemon might not be needed if we just need Display_Name for the message.
      // Let's get Display_Name before updating.

      $PokemonForMessage = $Pokemon; // Use already fetched data for message

      $Nickname_To_Set = (empty($Nickname) || $Nickname == '') ? null : Purify($Nickname); // Purify nickname here

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


    // Static helper methods (no $this->pdo needed)
    public static function CalculateStat($Stat_Name, $Base_Stat_Value, $Pokemon_Level, $Pokemon_Stat_IV, $Pokemon_Stat_EV, $Pokemon_Nature)
    {
      $Pokemon_Level = $Pokemon_Level < 1 ? 1 : $Pokemon_Level;
      $Pokemon_Stat_IV = $Pokemon_Stat_IV > 31 ? 31 : $Pokemon_Stat_IV;
      $Pokemon_Stat_EV = $Pokemon_Stat_EV > 252 ? 252 : $Pokemon_Stat_EV;

      if ( $Stat_Name == 'HP' ) {
        if ( $Base_Stat_Value == 1 ) return 1; // Shedinja case
        return floor((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + $Pokemon_Level + 10);
      } else {
        $Nature_Data = self::Natures()[$Pokemon_Nature];
        $Nature_Bonus = 1;
        if (isset($Nature_Data['Plus']) && $Nature_Data['Plus'] == $Stat_Name ) $Nature_Bonus = 1.1;
        else if (isset($Nature_Data['Minus']) && $Nature_Data['Minus'] == $Stat_Name ) $Nature_Bonus = 0.9;
        return floor(((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + 5) * $Nature_Bonus);
      }
    }

    public static function Natures()
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

    public static function GenerateNature()
    {
      $Nature_Keys = array_keys(self::Natures());
      return $Nature_Keys[mt_rand(0, count($Nature_Keys) - 1)];
    }

    public static function FetchLevel($Current_Exp, $Type) { global $Pokemon_Exp_Needed; if (isset($Pokemon_Exp_Needed[$Type])) { foreach ($Pokemon_Exp_Needed[$Type] as $Lvl => $Exp) { if ( $Current_Exp >= $Exp ) { continue; } else { return $Lvl - 1; } } } return 1; }
    public static function FetchExperience($Level, $Type) { global $Pokemon_Exp_Needed; if (isset($Pokemon_Exp_Needed[$Type][$Level])) { return $Pokemon_Exp_Needed[$Type][$Level]; } return 1; }
  }

[end of app/core/classes/pokemon_service.php]
