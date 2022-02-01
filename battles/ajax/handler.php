<?php
  error_reporting(-1);
  ini_set('display_errors', 'On');

  require_once '../classes/battle.php';

  spl_autoload_register(function($Class)
  {
    $Battle_Directory = dirname(__DIR__, 1);
    $Class = strtolower($Class);

    if (file_exists($Battle_Directory . "/classes/{$Class}.php"))
      require_once $Battle_Directory . "/classes/{$Class}.php";

    if (file_exists($Battle_Directory . "/fights/{$Class}.php"))
      require_once $Battle_Directory . "/fights/{$Class}.php";
  });

  require_once '../../core/required/session.php';

  if ( empty($_SESSION['Absolute']['Battle']) )
  {
    $Output['Message'] = 'You do not have a valid Battle session.';
    $_SESSION['Absolute']['Battle']['Dialogue'] = $Output['Message'];

    echo json_encode($Output);
  }

  $Fight = $_SESSION['Absolute']['Battle']['Battle_Type'];

  switch ($Fight)
  {
    case 'trainer':
      $Foe = $_SESSION['Absolute']['Battle']['Foe_ID'];
      $Battle = new Trainer($User_Data['ID'], $Foe);
      break;

    default:
      $Foe = $_SESSION['Absolute']['Battle']['Foe_ID'];
      $Battle = new Trainer($User_Data['ID'], $Foe);
      break;
  }

  $Output = [
    'Time_Started' => $_SESSION['Absolute']['Battle']['Time_Started'],
    'Battle_Layout' => empty($_SESSION['Absolute']['Battle']['Battle_Layout']) ? $User_Data['Battle_Theme'] : $_SESSION['Absolute']['Battle']['Battle_Layout'],
    'Battle_Type' => $_SESSION['Absolute']['Battle']['Battle_Type'],
    'Started' => $_SESSION['Absolute']['Battle']['Started'],
    'Battle_ID' => $_SESSION['Absolute']['Battle']['Battle_ID'],
    'Turn_ID' => $_SESSION['Absolute']['Battle']['Turn_ID'],
  ];

  /**
   * Process the desired battle action.
   */
  if
  (
    isset($_POST['Action']) &&
    $_POST['Action'] != 'null' &&
    isset($_POST['Data']) &&
    $_POST['Data'] != 'null'
  )
  {
    $Action = Purify($_POST['Action']);
    $Data = Purify($_POST['Data']);

    if ( isset($_POST['Battle_ID']) )
      $_SESSION['Absolute']['Battle']['Logging']['Battle_ID'] = Purify($_POST['Battle_ID']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['Battle_ID'] = 'Battle ID - Not Sent';

    if ( isset($_POST['Client_X']) )
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Client_X'] = Purify($_POST['Client_X']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Client_X'] = -1;

    if ( isset($_POST['Client_Y']) )
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Client_Y'] = Purify($_POST['Client_Y']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Client_Y'] = -1;

    if ( isset($_POST['Input_Type']) )
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Type'] = Purify($_POST['Input_Type']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Type'] = null;

    if ( isset($_POST['Is_Trusted']) )
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Is_Trusted'] = Purify($_POST['Is_Trusted']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['Input']['Is_Trusted'] = 0;

    if ( isset($_POST['In_Focus']) )
      $_SESSION['Absolute']['Battle']['Logging']['In_Focus'] = Purify($_POST['In_Focus']);
    else
      $_SESSION['Absolute']['Battle']['Logging']['In_Focus'] = 0;

    if ( !empty($_SESSION['Absolute']['Battle']['Postcodes']) )
    {
      if ( !empty($_SESSION['Absolute']['Battle']['Postcodes']['Continue']) )
        $Expected_Postcode = $_SESSION['Absolute']['Battle']['Postcodes']['Continue'];
      else
        $Expected_Postcode = $_SESSION['Absolute']['Battle']['Postcodes']['Restart'];

      $_SESSION['Absolute']['Battle']['Logging']['Postcode'] = [
        'Expected' => $Expected_Postcode,
        'Received' => str_replace('"', "", $Data)
      ];
    }

    $Battle->Log_Data->AddAction($Action);
    if ( !empty($_SESSION['Absolute']['Battle']['Postcodes']['Restart']) )
      $Battle->Log_Data->Finalize();

    $Turn_Data = $Battle->ProcessTurn($Action, $Data);

    $Output['Message'] = $Turn_Data;
  }
  else
  {
    if ( !empty($_SESSION['Absolute']['Battle']['Dialogue']) )
    {
      $Output['Message'] = $_SESSION['Absolute']['Battle']['Dialogue'];
    }
    else
    {
      $Output['Message'] = [
        'Type' => 'Success',
        'Text' => 'The battle has begun.'
      ];
    }
  }

  foreach ( ['Ally', 'Foe'] as $Side )
  {
    $Output[$Side] = $_SESSION['Absolute']['Battle'][$Side];
  }

  if ( !empty($_SESSION['Absolute']['Battle']['Weather']) )
  {
    $Output['Weather'] = $_SESSION['Absolute']['Battle']['Weather'];
  }

  if ( !empty($_SESSION['Absolute']['Battle']['Field_Effects']) )
  {
    $Output['Field_Effects'] = $_SESSION['Absolute']['Battle']['Field_Effects'];
  }

  if ( !empty($_SESSION['Absolute']['Battle']['Terrain']) )
  {
    $Output['Terrain'] = $_SESSION['Absolute']['Battle']['Terrain'];
  }

  $_SESSION['Absolute']['Battle']['Dialogue'] = $Output['Message'];

  echo json_encode($Output);
