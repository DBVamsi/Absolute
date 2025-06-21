<?php
  /**
   * Get and return all releasable Pokemon that the user has.
   */
  function GetReleasablePokemon()
  {
    global $Pokemon_Service, $User_Data; // Pokemon_Service is now used

    // The direct PDO query is now encapsulated in PokemonService
    $Releasable_Pokemon_Data = $Pokemon_Service->GetUserReleasablePokemonIDsAndBasicInfo($User_Data['ID']);

    // $Releasable_Pokemon_Data already contains the necessary formatted info.
    // If further processing of $Pokemon_Info was needed, it would be done here.
    // For now, the structure returned by GetUserReleasablePokemonIDsAndBasicInfo matches the old structure.

    return [
      'Amount' => $Releasable_Pokemon_Data['Amount'],
      'Pokemon' => $Releasable_Pokemon_Data['Pokemon']
    ];
  }

  /**
   * Process all of the Pokemon that were selected for release.
   *
   * @param $Selected_Pokemon
   */
  function ProcessSelectedPokemon
  (
    $Selected_Pokemon
  )
  {
    global $Pokemon_Service, $User_Data; // Pokemon_Service is now used

    if ( empty($Selected_Pokemon) )
    {
      return [
        'Success' => false,
        'Message' => 'You did not select any Pok&esacute;mon to release.'
      ];
    }

    $Selected_Pokemon_Array = json_decode($Selected_Pokemon);

    $_SESSION['EvoChroniclesRPG']['Release']['Releasable_Pokemon'] = [];

    foreach ( $Selected_Pokemon_Array as $Pokemon_ID_Json ) // Assuming $Pokemon is just an ID from JSON
    {
      $PokemonID = filter_var($Pokemon_ID_Json, FILTER_SANITIZE_NUMBER_INT);

      // The direct PDO query is now encapsulated in PokemonService
      $Is_Owned = $Pokemon_Service->CheckPokemonOwnership($PokemonID, $User_Data['ID']);

      if ( !$Is_Owned )
        continue;

      // GetPokemonData is now a service method
      $Pokemon_Info = $Pokemon_Service->GetPokemonData($PokemonID);
      if (!$Pokemon_Info) {
        continue; // Should not happen if ownership check passed, but good practice
      }

      $_SESSION['EvoChroniclesRPG']['Release']['Releasable_Pokemon'][] = [
        'ID' => $PokemonID, // Use the sanitized ID
        'Display_Name' => $Pokemon_Info['Display_Name'],
        'Gender' => $Pokemon_Info['Gender'],
        'Level' => $Pokemon_Info['Level']
      ];
    }

    return [
      'Pokemon' => $_SESSION['EvoChroniclesRPG']['Release']['Releasable_Pokemon']
    ];
  }

  /**
   * Finalize the release process of the selected Pokemon.
   */
  function FinalizeRelease()
  {
    global $User_Data, $Pokemon_Service; // Pokemon_Service is now used

    foreach ( $_SESSION['EvoChroniclesRPG']['Release']['Releasable_Pokemon'] as $Pokemon )
    {
      // ReleasePokemon is now a service method, and User_Data['ID'] is passed
      $Pokemon_Service->ReleasePokemon($Pokemon['ID'], $User_Data['ID']);
    }

    // Clear the session data after processing
    unset($_SESSION['EvoChroniclesRPG']['Release']['Releasable_Pokemon']);

    return [
      'Success' => true,
      'Message' => 'You have released the selected Pok&eacute;mon'
    ];
  }
