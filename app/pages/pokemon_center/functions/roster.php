<?php
  /**
   * Return the user's roster as a JSON object.
   */
  function GetRosterJSON()
  {
    global $User_Data, $Pokemon_Service; // Use PokemonService

    // Logic moved to PokemonService
    return $Pokemon_Service->GetUserRosterWithDetailedMoves($User_Data['ID']);
  }

  /**
   * Return the user's boxed Pokemon as a string.
   *
   * @param $Page
   */
  function GetBoxedPokemon
  (
    $Page
  )
  {
    global $User_Data, $Pokemon_Service; // Use PokemonService

    $Page = (int)$Page; // Purify removed, direct cast
    if ($Page < 1) {
      $Page = 1;
    }

    $Limit_Start = ($Page - 1) * 48;
    // The service method will handle the offset, not $Limit_Start directly for query.
    // The service method query should use OFFSET $Limit_Start LIMIT 48

    // Logic moved to PokemonService
    $Boxed_Pokemon = $Pokemon_Service->GetUserBoxedPokemonPaginated($User_Data['ID'], $Page, 48);


    // The count query for pagination should also ideally be in the service or a helper.
    // For now, assuming Pagination() function can work with a count or this query is acceptable.
    // This is a complex query to move directly without more info on Pagination() requirements.
    // For the purpose of this refactor, we'll assume the count part of pagination can be
    // refactored separately if needed. The main data fetching is moved.
    global $PDO; // Temporarily keep for pagination count if Pagination() needs $PDO.
                 // Ideally, Pagination would take the count directly.
    $Count_Query_String = 'SELECT COUNT(*) FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = "Box"';

    // If Pagination() can take a direct count:
    // $Total_Boxed_Pokemon = $Pokemon_Service->GetUserBoxedPokemonCount($User_Data['ID']);
    // $Pagination = Pagination($Total_Boxed_Pokemon, [], $User_Data['ID'], $Page, 48, ...);
    // For now, keeping existing pagination call structure:

    $Pagination = Pagination(
      $Count_Query_String, // This part is problematic as Pagination() might expect a query string it executes.
                           // If Pagination() executes SQL, it needs a PDO instance or to be refactored.
                           // Assuming for now it works or is out of scope for direct modification of its internals.
      [ $User_Data['ID'] ],
      $User_Data['ID'], // This param seems redundant if query is passed.
      $Page,
      48,
      3,
      'onclick="GetBoxedPokemon([PAGE]); return false;"',
      true
    );

    return [
      'Pagination' => $Pagination,
      'Boxed_Pokemon' => $Boxed_Pokemon, // This is now the direct result from the service.
      'Page' => $Page
    ];
  }

  /**
   * Return information about the specified Pokemon.
   *
   * @param $Pokemon_ID
   */
  function GetPokemonPreview
  (
    $Pokemon_ID_Input
  )
  {
    global $User_Data, $Pokemon_Service; // Use PokemonService

    $Pokemon_ID = (int)$Pokemon_ID_Input; // Cast to int

    if ( empty($Pokemon_ID) )
    {
      return [
        'Pokemon_Data' => 'Select a valid Pok&eacute;mon to preview.'
      ];
    }

    // Fetch Pokemon data using the service. This includes ownership data.
    $Pokemon_Info = $Pokemon_Service->GetPokemonData($Pokemon_ID);

    if ( empty($Pokemon_Info) || $Pokemon_Info['Owner_Current'] != $User_Data['ID'] )
    {
      return [
        'Pokemon_Data' => 'This Pok&eacute;mon does not exist or does not belong to you.'
      ];
    }

    // FetchLevel is now a static method on PokemonService
    $Pokemon_Level = number_format(PokemonService::FetchLevel($Pokemon_Info['Experience_Raw'], 'Pokemon'));

    $Item_Icon_Html = ''; // Changed variable name to avoid conflict
    if ( !empty($Pokemon_Info['Item_ID']) && !empty($Pokemon_Info['Item_Icon']) )
    {
      $Item_Icon_Html = "
        <div class='border-gradient' style='height: 28px; width: 28px;'>
          <div>
            <img src='" . htmlspecialchars($Pokemon_Info['Item_Icon'], ENT_QUOTES, 'UTF-8') . "' />
          </div>
        </div>
      ";
    }

    $Roster_Slots_Html = ''; // Changed variable name
    if ( $User_Data['Roster'] ) // $User_Data['Roster'] is populated from session.php via User_Class
    {
      for ( $i = 0; $i < 6; $i++ )
      {
        if ( isset($User_Data['Roster'][$i]['ID'])  )
        {
          // GetPokemonData for roster slot Pokemon
          $Roster_Slot_Pokemon = $Pokemon_Service->GetPokemonData($User_Data['Roster'][$i]['ID']);

          if ($Roster_Slot_Pokemon) {
            $Roster_Slots_Html .= "
              <div class='border-gradient hover' style='height: 32px; width: 42px;'>
                <div style='padding: 2px;'>
                  <img src='" . htmlspecialchars($Roster_Slot_Pokemon['Icon'], ENT_QUOTES, 'UTF-8') . "' onclick=\"MovePokemon({$Pokemon_Info['ID']}, " . ($i + 1) . ");\" />
                </div>
              </div>
            ";
          }
        }
        else
        {
          $Default_Icon = DOMAIN_SPRITES . "/Pokemon/Sprites/0_mini.png";
          $Roster_Slots_Html .= "
            <div class='border-gradient hover' style='height: 32px; width: 42px;'>
              <div style='padding: 2px;'>
                <img src='" . htmlspecialchars($Default_Icon, ENT_QUOTES, 'UTF-8') . "' style='height: 30px; width: 40px;' onclick=\"MovePokemon({$Pokemon_Info['ID']}, " . ($i + 1) . ");\" />
              </div>
            </div>
          ";
        }
      }
    }
    else
    {
      for ( $i = 0; $i < 6; $i++ )
      {
        $Default_Icon_Big = DOMAIN_SPRITES . "/Pokemon/Sprites/0.png";
        $Roster_Slots_Html .= "
          <td colspan='1' style='width: calc(100% / 6);'>
            <img src='" . htmlspecialchars($Default_Icon_Big, ENT_QUOTES, 'UTF-8') . "' style='filter: grayscale(100%);' />
          </td>
        ";
      }
    }

    // Ensure all outputs are escaped
    $Pokemon_Sprite_Escaped = htmlspecialchars($Pokemon_Info['Sprite'], ENT_QUOTES, 'UTF-8');
    $Ajax_Url_Escaped = htmlspecialchars(DOMAIN_ROOT . "/core/ajax/pokemon.php?id=" . $Pokemon_Info['ID'], ENT_QUOTES, 'UTF-8');
    $Gender_Icon_Escaped = htmlspecialchars($Pokemon_Info['Gender_Icon'], ENT_QUOTES, 'UTF-8');

    return [
        'Pokemon_Data' => "
          <div class='flex' style='flex-basis: 100%; gap: 6px;'>
            <div class='flex' style='align-items: center; flex-basis: 175px; flex-wrap: wrap; justify-content: center;'>
              <div class='flex' style='align-items: center; gap: 10px; justify-content: center;'>
                <div class='border-gradient hover hw-96px padding-0px'>
                  <div>
                    <img class='popup' src='{$Pokemon_Sprite_Escaped}' data-src='{$Ajax_Url_Escaped}' />
                  </div>
                </div>

                <div class='flex' style='flex-basis: 30px; flex-wrap: wrap; gap: 35px 0px;'>
                  <div class='border-gradient hw-30px' style='height: 28px; width: 28px;'>
                    <div>
                      <img src='{$Gender_Icon_Escaped}' style='height: 24px; width: 24px;' />
                    </div>
                  </div>
                  {$Item_Icon_Html}
                </div>
              </div>

              <div style='flex-basis: 100%;'>
                <b>Level</b><br />
                {$Pokemon_Level}<br />
                <i style='font-size: 12px;'>(" . htmlspecialchars($Pokemon_Info['Experience'], ENT_QUOTES, 'UTF-8') . " Exp)</i>
              </div>
            </div>

            <div class='flex' style='align-items: center; flex-basis: 120px; flex-wrap: wrap; gap: 10px; justify-content: flex-start;'>
              <b>Add To Roster</b><br />
              {$Roster_Slots_Html}
            </div>

            <div style='flex-basis: 40%;'>
              <table class='border-gradient' style='width: 100%;'>
                <thead>
                  <tr>
                    <th style='width: 25%;'>Stat</th>
                    <th style='width: 25%;'>Base</th>
                    <th style='width: 25%;'>IV</th>
                    <th style='width: 25%;'>EV</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td style='padding: 3px;'><b>HP</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][0]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][0]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][0]) . "</td>
                  </tr>
                  <tr>
                    <td style='padding: 3px;'><b>Attack</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][1]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][1]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][1]) . "</td>
                  </tr>
                  <tr>
                    <td style='padding: 3px;'><b>Defense</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][2]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][2]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][2]) . "</td>
                  </tr>
                  <tr>
                    <td style='padding: 3px;'><b>Sp. Att</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][3]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][3]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][3]) . "</td>
                  </tr>
                  <tr>
                    <td style='padding: 3px;'><b>Sp. Def</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][4]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][4]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][4]) . "</td>
                  </tr>
                  <tr>
                    <td style='padding: 3px;'><b>Speed</b></td>
                    <td>" . number_format($Pokemon_Info['Stats'][5]) . "</td>
                    <td>" . number_format($Pokemon_Info['IVs'][5]) . "</td>
                    <td>" . number_format($Pokemon_Info['EVs'][5]) . "</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        "
      ];
  }
