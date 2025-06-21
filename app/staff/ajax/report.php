<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  // require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/report.php'; // Removed

  if ( !isset($User_Data) ) // Ensure User_Data is set (user is logged in and is staff)
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'You must be logged in as staff to perform this action.',
    ]);
    exit;
  }

  if ( !empty($_GET['Report_ID']) )
    $Report_ID = (int)($_GET['Report_ID']); // Cast to int
  else
    $Report_ID = 0;

  if ( empty($Report_ID) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'The report you are trying to delete requires a valid Report ID.',
    ]);

    exit;
  }

  // Use ReportService to check existence
  $Report_Existence = $Report_Service->CheckReportExistence($Report_ID);

  if ( empty($Report_Existence) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'This report no longer exists.',
    ]);

    exit;
  }

  // Use ReportService to delete, passing acting staff user ID
  $Delete_Result = $Report_Service->DeleteReport($Report_ID, $User_Data['ID']);

  if ( $Delete_Result['Success'] )
  {
    // Use ReportService to get and show updated list
    $Active_Reports = $Report_Service->GetActiveReports();
    $Report_List_HTML = $Report_Service->ShowActiveReports($Active_Reports);

    echo json_encode([
      'Success' => true,
      'Message' => "This report has been deleted.",
      'Active_Report_List' => $Report_List_HTML,
    ]);
  }
  else
  {
    echo json_encode([
      'Success' => false,
      'Message' => $Delete_Result['Message'],
    ]);
  }
