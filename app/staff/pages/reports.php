<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/auth.php';

  if ( !AuthorizeUser() )
  {
    echo "
      <div style='padding: 5px;'>
        You aren't authorized to be here.
      </div>
    ";

    exit;
  }

  // require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/report.php'; // Removed

  $Active_Reports = $Report_Service->GetActiveReports(); // Updated
?>

<div style='display: flex; flex-wrap: wrap; justify-content: center;'>
  <div style='flex-basis: 100%; width: 100%;'>
    <h3>Active Reports</h3>
  </div>

  <div style='flex-basis: 100%; width: 100%;'>
    <h4>There are <?= number_format(count($Active_Reports)); ?> active reports.</h3>
  </div>

  <div id='Report_AJAX'></div>

  <div style='display: flex; flex-wrap: wrap; gap: 10px;' id='Active_Report_List'>
    <?php
      echo $Report_Service->ShowActiveReports($Active_Reports); // Updated
    ?>
  </div>
</div>
