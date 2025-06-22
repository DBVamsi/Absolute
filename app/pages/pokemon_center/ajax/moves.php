<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/pages/pokemon_center/functions/roster.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/pages/pokemon_center/functions/moves.php';

  $Action_Input = $_GET['Action'] ?? '';
  $allowed_actions = ['Get_Roster', 'Select_Move', 'Update_Move'];
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

  $Pokemon_ID = null;
  if ( !empty($_GET['Pokemon_ID']) )
    $Pokemon_ID = (int)$_GET['Pokemon_ID'];

  $Move_Slot = 1;
  // Ensure Move_Slot is validated from GET param before use, then cast to int
  if ( !empty($_GET['Move_Slot']) ) {
      $temp_move_slot = (int)$_GET['Move_Slot'];
      if (in_array($temp_move_slot, [1, 2, 3, 4], true)) {
          $Move_Slot = $temp_move_slot;
      }
      // else $Move_Slot remains 1 (default) or handle error
  }


  $Move_ID = null;
  if ( !empty($_GET['Move_ID']) )
    $Move_ID = (int)$_GET['Move_ID'];

  if ( $Move_ID !== null && $Move_ID != 0 ) // Allow 0 for unsetting a move, but check existence if not 0
  {
    try
    {
      $Check_Move_Existence = $PDO->prepare("
        SELECT `ID`
        FROM `moves`
        WHERE `ID` = ?
        LIMIT 1
      ");
      $Check_Move_Existence->execute([ $Move_ID ]);
      $Check_Move_Existence->setFetchMode(PDO::FETCH_ASSOC);
      $Move_Existence = $Check_Move_Existence->fetch();
    }
    catch ( PDOException $e )
    {
      HandleError($e);
    }

    if ( empty($Move_Existence) )
    {
      echo json_encode([
        'Success' => false,
        'Message' => 'An invalid move was selected.'
      ]);

      exit;
    }
  }

  switch ( $Action )
  {
    case 'Get_Roster':
      echo json_encode([
        GetRosterJSON()
      ]);
      break;

    case 'Select_Move':
      echo json_encode([
        'Dropdown_HTML' => GetMoveDropdown($Pokemon_ID, $Move_Slot)
      ]);
      break;

    case 'Update_Move':
      $Update_Move = UpdatePokemonMove($Pokemon_ID, $Move_Slot, $Move_ID);

      echo json_encode([
        'Success' => $Update_Move['Success'],
        'Message' => $Update_Move['Message'],
      ]);
      break;
  }
