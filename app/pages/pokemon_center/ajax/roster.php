<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/pages/pokemon_center/functions/roster.php';

  $Action_Input = $_GET['Action'] ?? '';
  $allowed_actions = ['Get_Roster', 'Get_Box', 'Move_Pokemon', 'Preview_Pokemon'];
  $Action = null;

  if ( !empty($Action_Input) && in_array($Action_Input, $allowed_actions, true) )
    $Action = $Action_Input; // Whitelisted, Purify not needed

  if ( empty($Action) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'An invalid action was selected.',
    ]);

    exit;
  }

  $Page = 1;
  if ( !empty($_GET['Page']) ) {
    $Page_Input = (int)$_GET['Page'];
    $Page = ($Page_Input > 0) ? $Page_Input : 1;
  }

  $Pokemon_ID = null;
  if ( !empty($_GET['Pokemon_ID']) )
    $Pokemon_ID = (int)$_GET['Pokemon_ID'];

  $Slot = 1;
  if ( !empty($_GET['Slot']) ) {
    $Slot_Input = (int)$_GET['Slot'];
    // Assuming slots can be from 1 to 7 (6 for roster, 7 for box)
    $Slot = ($Slot_Input > 0 && $Slot_Input <= 7) ? $Slot_Input : 1;
  }

  switch ( $Action )
  {
    case 'Get_Box':
      echo json_encode([
        GetBoxedPokemon($Page)
      ]);
      break;

    case 'Get_Roster':
      echo json_encode([
        GetRosterJSON()
      ]);
      break;

    case 'Move_Pokemon':
      $Move_Pokemon = MovePokemon($Pokemon_ID, $Slot);

      echo json_encode([
        'Success' => $Move_Pokemon['Type'],
        'Message' => $Move_Pokemon['Message']
      ]);
      break;

    case 'Preview_Pokemon':
      echo json_encode([
        GetPokemonPreview($Pokemon_ID)
      ]);
      break;
  }
