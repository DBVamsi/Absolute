<?php
  /**
   * Calculate the value of the specified stat.
   *
   * @param $Stat_Name
   * @param $Base_Stat_Value
   * @param $Pokemon_Level
   * @param $Pokemon_Stat_IV
   * @param $Pokemon_Stat_EV
   * @param $Pokemon_Nature
   */
  function CalculateStat // Remains global, or could be moved to a static helper class. Called by PokemonService::CalculateStat
  (
    $Stat_Name,
    $Base_Stat_Value,
    $Pokemon_Level,
    $Pokemon_Stat_IV,
    $Pokemon_Stat_EV,
    $Pokemon_Nature
  )
  {
    $Pokemon_Level = $Pokemon_Level < 1 ? 1 : $Pokemon_Level;
    $Pokemon_Stat_IV = $Pokemon_Stat_IV > 31 ? 31 : $Pokemon_Stat_IV;
    $Pokemon_Stat_EV = $Pokemon_Stat_EV > 252 ? 252 : $Pokemon_Stat_EV;

    if ( $Stat_Name == 'HP' )
    {
      if ( $Base_Stat_Value == 1 )
        return 1;

      return floor((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + $Pokemon_Level + 10);
    }
    else
    {
      $Nature_Data = Natures()[$Pokemon_Nature]; // Calls global Natures()

      if ( $Nature_Data['Plus'] == $Stat_Name )
        $Nature_Bonus = 1.1;
      else if ( $Nature_Data['Minus'] == $Stat_Name )
        $Nature_Bonus = 0.9;
      else
        $Nature_Bonus = 1;

      return floor(((((2 * $Base_Stat_Value + $Pokemon_Stat_IV + ($Pokemon_Stat_EV / 4)) * $Pokemon_Level) / 100) + 5) * $Nature_Bonus);
    }
  }

  /**
   * Returns an array of all natures and the stats that they modify.
   */
  function Natures() // Remains global, or could be moved to a static helper class. Called by PokemonService::Natures()
  {
    return [
      'Adamant' => [
        'Plus' => 'Attack',
        'Minus' => 'SpAttack'
      ],
      'Brave' => [
        'Plus' => 'Attack',
        'Minus' => 'Speed'
      ],
      'Lonely' => [
        'Plus' => 'Attack',
        'Minus' => 'Defense'
      ],
      'Naughty' => [
        'Plus' => 'Attack',
        'Minus' => 'SpDefense'
      ],

      'Bold' => [
        'Plus' => 'Defense',
        'Minus' => 'Attack'
      ],
      'Impish' => [
        'Plus' => 'Defense',
        'Minus' => 'SpAttack'
      ],
      'Lax' => [
        'Plus' => 'Defense',
        'Minus' => 'SpDefense'
      ],
      'Relaxed' => [
        'Plus' => 'Defense',
        'Minus' => 'Speed'
      ],

      'Modest' => [
        'Plus' => 'SpAttack',
        'Minus' => 'Attack'
      ],
      'Mild' => [
        'Plus' => 'SpAttack',
        'Minus' => 'Defense'
      ],
      'Quiet' => [
        'Plus' => 'SpAttack',
        'Minus' => 'Speed'
      ],
      'Rash' => [
        'Plus' => 'SpAttack',
        'Minus' => 'SpDefense'
      ],

      'Calm' => [
        'Plus' => 'SpDefense',
        'Minus' => 'Attack'
      ],
      'Careful' => [
        'Plus' => 'SpDefense',
        'Minus' => 'SpAttack'
      ],
      'Gentle' => [
        'Plus' => 'SpDefense',
        'Minus' => 'Defense'
      ],
      'Sassy' => [
        'Plus' => 'SpDefense',
        'Minus' => 'Speed'
      ],

      'Hasty' => [
        'Plus' => 'Speed',
        'Minus' => 'Defense'
      ],
      'Jolly' => [
        'Plus' => 'Speed',
        'Minus' => 'SpAttack'
      ],
      'Naive' => [
        'Plus' => 'Speed',
        'Minus' => 'SpDefense'
      ],
      'Timid' => [
        'Plus' => 'Speed',
        'Minus' => 'Attack'
      ],

      'Bashful' => [
        'Plus' => null,
        'Minus' => null
      ],
      'Docile' => [
        'Plus' => null,
        'Minus' => null
      ],
      'Hardy' => [
        'Plus' => null,
        'Minus' => null
      ],
      'Quirky' => [
        'Plus' => null,
        'Minus' => null
      ],
      'Serious' => [
        'Plus' => null,
        'Minus' => null
      ],
    ];
  }

  // Functions removed:
  // CreatePokemon
  // GetAbilities
  // GetBaseStats
  // GetCurrentStats
  // GetMoveData
  // GetPokedexData
  // GetPokemonData
  // GetSprites
  // GenerateAbility
  // GenerateGender
  // GenerateNature (this was made static in PokemonService, so original global is removed)
  // MovePokemon
  // ReleasePokemon
?>
