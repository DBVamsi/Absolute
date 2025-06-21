<?php
  /**
   * Update the specified Pokemon's nickname.
   *
   * @param $Pokemon_ID
   * @param $Nickname
   */
  function UpdateNickname
  (
    $Pokemon_ID,
    $Nickname
  )
  {
    global $User_Data, $Pokemon_Service; // Use PokemonService

    // Ensure Pokemon_ID is an integer
    $Pokemon_ID = (int)$Pokemon_ID;

    // Nickname purification/validation should happen before calling the service,
    // or within the service method itself if it's complex.
    // For now, we'll assume $Nickname is passed as is, and Purify was used in the AJAX handler.
    // The service method will handle the empty/null logic for Nickname.

    $Result = $Pokemon_Service->UpdatePokemonNickname($Pokemon_ID, $User_Data['ID'], $Nickname);

    // The service method UpdatePokemonNickname should return an array like ['Success' => bool, 'Message' => string]
    return $Result;
  }

[end of app/pages/pokemon_center/functions/nickname.php]
