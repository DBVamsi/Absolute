<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  // require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/maintenance.php'; // Removed

  if ( !isset($User_Data) ) // Ensure User_Data is set (user is logged in and is staff)
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'You must be logged in as staff to perform this action.',
    ]);
    exit;
  }

  if ( !empty($_GET['Page_ID']) )
    $Page_ID = (int)($_GET['Page_ID']); // Cast to int
  else
    $Page_ID = 0;

  if ( empty($Page_ID) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'The page you are trying to modify requires a valid Page ID.',
    ]);

    exit;
  }

  if ( !empty($_GET['Page_Action']) && in_array($_GET['Page_Action'], ['Toggle']) )
    $Page_Action = Purify($_GET['Page_Action']);
  else
    $Page_Action = null;

  if ( empty($Page_Action) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'An invalid action was selected.',
    ]);

    exit;
  }

  // Use MaintenanceService to check page existence
  $Page_Existence = $Maintenance_Service->CheckPageExistence($Page_ID);

  if ( empty($Page_Existence) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'This page does not exist.',
    ]);

    exit;
  }

  switch ( $Page_Action )
  {
    case 'Toggle':
      // Use MaintenanceService to toggle page maintenance
      $Toggle_Result = $Maintenance_Service->TogglePageMaintenance($Page_Existence['ID'], $User_Data['ID']);

      if ( $Toggle_Result['Success'] )
      {
        echo json_encode([
          'Success' => true,
          'Message' => $Toggle_Result['Message'],
          'Page_ID' => $Page_Existence['ID'],
          'New_Status' => $Toggle_Result['New_Status']
        ]);
      }
      else
      {
        echo json_encode([
          'Success' => false,
          'Message' => $Toggle_Result['Message'],
        ]);
      }
      break;
  }
