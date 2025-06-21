<?php
  require_once '../../core/required/session.php'; // $pdo_instance, $User_Class, $Pokemon_Service are created here.

  spl_autoload_register(function($Class)
  {
    $Map_Directory = dirname(__DIR__, 1);
    $Class = strtolower($Class);

    if (file_exists($Map_Directory . "/classes/{$Class}.php"))
      require_once $Map_Directory . "/classes/{$Class}.php";
  });

  // Instantiate Player with PDO
  $Player_Instance = new Player($pdo_instance);
  // Instantiate Map with Player instance
  $Map_Instance = new Map($Player_Instance);
  // Instantiate Encounter service with PDO, User_Class instance, and Pokemon_Service instance
  $Encounter_Service = new Encounter($pdo_instance, $User_Class, $Pokemon_Service);


  /**
   * Handle loading.
   */
  if ( isset($_GET['Request']) )
  {
    $Request = Purify($_GET['Request']); // String, Purify is okay for now

    switch ( $Request )
    {
      case 'Load':
        header('Content-Type: application/json');
        echo json_encode($Map_Instance->Load());
        break;

      case 'Stats':
        header('Content-Type: application/json');
        echo json_encode($Map_Instance->Stats());
        break;
    }

    exit;
  }

  /**
   * Generate an encounter for the player.
   */
  if ( isset($_GET['Encounter']) )
  {
    header('Content-Type: application/json');

    $Encounter_Zone = Purify($_GET['Encounter']); // String, Purify is okay for now
    if ( strlen($Encounter_Zone) > 1 )
    {
      echo json_encode([ 'Generated_Encounter' => 'Invalid Encounter' ]);
    }
    else
    {
      $Steps_Till_Encounter = $Map_Instance->Player->GetStepsTillEncounter();
      if ( $Steps_Till_Encounter !== -1 )
      {
        echo json_encode([ 'Generated_Encounter' => 'Invalid Encounter' ]);
      }
      else
      {
        $Encounter = $Encounter_Service->Generate($Map_Instance->Player->GetMap(), $Map_Instance->Player->GetMapLevelAndExp()['Map_Level'], $Encounter_Zone);
        echo json_encode([ 'Generated_Encounter' => $Encounter ]);
      }
    }

    exit;
  }

  /**
   * Perform some server-side validation.
   */
  if ( isset($_POST['Action']) )
  {
    $Action = Purify($_POST['Action']); // String, Purify is okay for now

    switch ( $Action )
    {
      /**
       * Handle object interaction.
       */
      case 'Interact':
        $x = (int)floor($_POST['x']);
        $y = (int)floor($_POST['y']);
        $z = (int)$_POST['z']; // Assuming z is already an integer or needs int cast

        $Interaction_Check = $Map_Instance->Player->CheckInteraction($x, $y, $z);

        header('Content-Type: application/json');
        echo json_encode($Interaction_Check);
        break;

      /**
       * Handle player movement.
       *  - Update player's map coordinates.
       *  - Check for encounters.
       */
      case 'Movement':
        $x = (int)floor($_POST['x']);
        $y = (int)floor($_POST['y']) + 1;
        $z = (int)$_POST['z']; // Assuming z is already an integer or needs int cast

        $Map_Instance->Player->SetPosition($x, $y, $z);
        $User_Class->UpdateStat($User_Data['ID'], 'Map_Steps_Taken', 1);

        $Encounter_Tile = Purify($_POST['Encounter_Tile']); // String 'true'/'false', Purify is okay for now
        if ( isset($Encounter_Tile) && $Encounter_Tile === 'true' )
          $Map_Instance->Player->SetStepsTillEncounter();

        header('Content-Type: application/json');
        echo json_encode($Map_Instance->Stats());
        break;

      /**
       * Handle map warping.
       */
      case 'Warp':
        $x = (int)floor($_POST['x']);
        $y = (int)floor($_POST['y']);
        $z = (int)$_POST['z']; // Assuming z is already an integer or needs int cast

        $Warp_Data = false;
        $Warp_Tile = Purify($_POST['Warp_Tile']); // String 'true'/'false', Purify is okay for now
        if ( isset($Warp_Tile) && $Warp_Tile === 'true' )
          $Warp_Data = $Map_Instance->Player->ProcessWarp($x, $y, $z, $Warp_Tile);

        header('Content-Type: application/json');
        echo json_encode($Warp_Data);
        break;

      /**
       * Catch the active encounter.
       */
      case 'Catch':
        $Catch_Encounter = $Encounter_Service->Catch();
        header('Content-Type: application/json');
        echo json_encode($Catch_Encounter);
        break;

      /**
       * Release the active encounter.
       */
      case 'Release':
        $Release_Encounter = $Encounter_Service->Release();
        header('Content-Type: application/json');
        echo json_encode($Release_Encounter);
        break;

      /**
       * Run from the active encounter.
       */
      case 'Run':
        $Run_From_Encounter = $Encounter_Service->Run();
        header('Content-Type: application/json');
        echo json_encode($Run_From_Encounter);
        break;

      default:
        break;
    }

    exit;
  }
