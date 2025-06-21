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

  if ( empty($_SESSION['EvoChroniclesRPG']['Battle']) )
  {
    $Output['Message'] = 'You do not have a valid Battle session.';
    $_SESSION['EvoChroniclesRPG']['Battle']['Dialogue'] = $Output['Message'];

    echo json_encode($Output);
    exit; // Added exit after sending JSON response
  }

  $Fight = $_SESSION['EvoChroniclesRPG']['Battle']['Battle_Type'];

  // Assuming Trainer class constructor takes $pdo_instance, $Ally_ID, $Foe_ID
  // $User_Data is available from session.php
  // $pdo_instance is available from session.php
  switch ($Fight)
  {
    case 'trainer':
      $Foe_ID = $_SESSION['EvoChroniclesRPG']['Battle']['Foe_ID'];
      $Battle = new Trainer($pdo_instance, $User_Data['ID'], $Foe_ID); // Assuming Trainer needs pdo_instance
      break;
    case 'wild': // Assuming a Wild class exists or Trainer is a default
      $Foe_ID = $_SESSION['EvoChroniclesRPG']['Battle']['Foe_ID'] ?? null; // Wild might not have a Foe_ID in the same way
      $Battle = new Wild($pdo_instance, $User_Data['ID']); // Assuming Wild class and its constructor
      break;
    default:
      // Fallback or error handling if $Fight type is unknown
      echo json_encode(['Type' => 'Error', 'Text' => 'Unknown battle type.']);
      exit;
  }

  $Output = [
    'Time_Started' => $_SESSION['EvoChroniclesRPG']['Battle']['Time_Started'],
    'Battle_Layout' => empty($_SESSION['EvoChroniclesRPG']['Battle']['Battle_Layout']) ? $User_Data['Battle_Theme'] : $_SESSION['EvoChroniclesRPG']['Battle']['Battle_Layout'],
    'Battle_Type' => $_SESSION['EvoChroniclesRPG']['Battle']['Battle_Type'],
    'Started' => $_SESSION['EvoChroniclesRPG']['Battle']['Started'],
    'Battle_ID' => $_SESSION['EvoChroniclesRPG']['Battle']['Battle_ID'],
    'Turn_ID' => $_SESSION['EvoChroniclesRPG']['Battle']['Turn_ID'],
  ];

  /**
   * Process the desired battle action.
   */
  if
  (
    isset($_POST['Action']) &&
    $_POST['Action'] != 'null' &&
    isset($_POST['Data'])
    // Removed $_POST['Data'] != 'null' check as JSON "null" is a valid value to be purified.
  )
  {
    $Action = Purify($_POST['Action']); // Action is likely a string command
    // Data can be various types (int for move slot, string for postcode, object for item use)
    // Purify will handle basic XSS; further type validation might be in ProcessTurn or handlers
    $Data = Purify($_POST['Data']);

    if ( isset($_POST['Battle_ID']) )
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Battle_ID'] = Purify($_POST['Battle_ID']); // String
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Battle_ID'] = 'Battle ID - Not Sent';

    if ( isset($_POST['Client_X']) )
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Client_X'] = (int)$_POST['Client_X']; // Cast to int
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Client_X'] = -1;

    if ( isset($_POST['Client_Y']) )
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Client_Y'] = (int)$_POST['Client_Y']; // Cast to int
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Client_Y'] = -1;

    if ( isset($_POST['Input_Type']) )
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Type'] = Purify($_POST['Input_Type']); // String
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Type'] = null;

    if ( isset($_POST['Is_Trusted']) ) // Boolean-like (true/false as string or 1/0)
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Is_Trusted'] = filter_var(Purify($_POST['Is_Trusted']), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Input']['Is_Trusted'] = 0;

    if ( isset($_POST['In_Focus']) ) // Boolean-like
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['In_Focus'] = filter_var(Purify($_POST['In_Focus']), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    else
      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['In_Focus'] = 0;

    if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Postcodes']) )
    {
      if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Postcodes']['Continue']) )
        $Expected_Postcode = $_SESSION['EvoChroniclesRPG']['Battle']['Postcodes']['Continue'];
      else
        $Expected_Postcode = $_SESSION['EvoChroniclesRPG']['Battle']['Postcodes']['Restart'];

      // $Data is already purified. If it's a JSON string, str_replace might be okay.
      // If it's already an object/array due to Purify handling, this needs care.
      // Assuming $Data for postcode comparison is expected to be a simple string after Purify.
      $Received_Postcode = is_string($Data) ? str_replace('"', "", $Data) : '';

      $_SESSION['EvoChroniclesRPG']['Battle']['Logging']['Postcode'] = [
        'Expected' => $Expected_Postcode,
        'Received' => $Received_Postcode
      ];
    }

    if (isset($Battle->Log_Data) && is_object($Battle->Log_Data) && method_exists($Battle->Log_Data, 'AddAction')) {
        $Battle->Log_Data->AddAction($Action);
        if (!empty($_SESSION['EvoChroniclesRPG']['Battle']['Postcodes']['Restart'])) {
            $Battle->Log_Data->Finalize();
        }
    }


    $Turn_Data = $Battle->ProcessTurn($Action, $Data);

    $Output['Message'] = $Turn_Data;
  }
  else
  {
    if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Dialogue']) )
    {
      $Output['Message'] = $_SESSION['EvoChroniclesRPG']['Battle']['Dialogue'];
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
    if (isset($_SESSION['EvoChroniclesRPG']['Battle'][$Side])) {
      $Output[$Side] = $_SESSION['EvoChroniclesRPG']['Battle'][$Side];
    } else {
      $Output[$Side] = null; // Or some default state
    }
  }

  if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Weather']) )
  {
    $Output['Weather'] = $_SESSION['EvoChroniclesRPG']['Battle']['Weather'];
  }

  if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Field_Effects']) )
  {
    $Output['Field_Effects'] = $_SESSION['EvoChroniclesRPG']['Battle']['Field_Effects'];
  }

  if ( !empty($_SESSION['EvoChroniclesRPG']['Battle']['Terrain']) )
  {
    $Output['Terrain'] = $_SESSION['EvoChroniclesRPG']['Battle']['Terrain'];
  }

  $_SESSION['EvoChroniclesRPG']['Battle']['Dialogue'] = $Output['Message'];

  echo json_encode($Output);
