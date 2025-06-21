<?php
  /**
   * Return an HTML select dropdown menu of all available moves.
   *
   * @param $Pokemon_ID
   * @param $Move_Slot
   */
  function GetMoveDropdown
  (
    $Pokemon_ID,
    $Move_Slot
  )
  {
    global $Pokemon_Service; // Use PokemonService

    $Move_List = $Pokemon_Service->GetAllUsableMoves();
    if ( $Move_List === false ) // Error fetching moves
    {
      return "Error loading moves.";
    }

    $Move_Options = "";
    if ( !empty($Move_List) )
    {
      foreach ( $Move_List as $Move_Data )
      {
        // Ensure that $Move_Data['Name'] is properly escaped if it can contain special HTML characters
        $Move_Name_Escaped = htmlspecialchars($Move_Data['Name'], ENT_QUOTES, 'UTF-8');
        $Move_Options .= "<option value='{$Move_Data['ID']}'>{$Move_Name_Escaped}</option>";
      }
    }

    // Pokemon_ID and Move_Slot are numbers, generally safe in HTML attributes here,
    // but good practice would be to ensure they are (int) if coming from user input elsewhere.
    return "
      <select name='{$Pokemon_ID}_Move_{$Move_Slot}' onchange='UpdateMoveSlot({$Pokemon_ID}, {$Move_Slot});'>
        <option value='0'>Select A Move</option> <!-- Added value 0 for "Select A Move" -->
        {$Move_Options}
      </select>
    ";
  }

  /**
   * Update the specified Pokemon's move.
   *
   * @param $Pokemon_ID
   * @param $Move_Slot - Expected to be 1, 2, 3, or 4
   * @param $Move_ID
   */
  function UpdatePokemonMove
  (
    $Pokemon_ID,
    $Move_Slot, // This is the slot number (1-4)
    $Move_ID
  )
  {
    global $User_Data, $Pokemon_Service; // Use PokemonService

    // Validate Move_Slot (expecting 1, 2, 3, or 4)
    if ( !in_array((int)$Move_Slot, [1, 2, 3, 4], true) )
    {
      return [
        'Success' => false,
        'Message' => 'Invalid move slot selected.'
      ];
    }

    // $Move_ID should be an integer. If it's 0, it means unsetting the move.
    $Move_ID = (int)$Move_ID;


    $Update_Result = $Pokemon_Service->UpdatePokemonMoveSlot($Pokemon_ID, $User_Data['ID'], (int)$Move_Slot, $Move_ID);

    // UpdatePokemonMoveSlot in service should return an array like ['Success' => bool, 'Message' => string]
    return $Update_Result;
  }

[end of app/pages/pokemon_center/functions/moves.php]
