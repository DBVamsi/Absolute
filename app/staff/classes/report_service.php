<?php
  class ReportService
  {
    private $pdo;
    private $userService;
    private $staffLogService;

    public function __construct(PDO $pdo, User $userService, StaffLogService $staffLogService)
    {
      $this->pdo = $pdo;
      $this->userService = $userService;
      $this->staffLogService = $staffLogService;
    }

    /**
     * Fetch a report by its ID to check if it exists.
     * @param int $Report_ID
     * @return array|false
     */
    public function CheckReportExistence(int $Report_ID)
    {
      try
      {
        $Fetch_Report = $this->pdo->prepare("SELECT * FROM `reports` WHERE `ID` = ? LIMIT 1");
        $Fetch_Report->execute([ $Report_ID ]);
        $Fetch_Report->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_Report->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Fetch all active reports.
     */
    public function GetActiveReports()
    {
      try
      {
        $Fetch_Reports = $this->pdo->prepare("SELECT * FROM `reports` WHERE `Status` = 'Active' ORDER BY `Timestamp` ASC");
        $Fetch_Reports->execute([]);
        $Fetch_Reports->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_Reports->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Display all active reports.
     * @param array $Active_Reports
     */
    public function ShowActiveReports(array $Active_Reports)
    {
      if ( empty($Active_Reports) )
      {
        return "
          <table class='border-gradient' style='width: 800px;'>
            <tbody>
              <tr>
                <td colspan='3' style='padding: 10px;'>
                  There are no active reports.
                </td>
              </tr>
            </tbody>
          </table>
        ";
      }
      else
      {
        $Report_List_Text = '';
        foreach ( $Active_Reports as $Report )
        {
          // It's possible FetchUserData might return false, handle this.
          $User_Reporting_Data = $this->userService->FetchUserData($Report['User_Reporting']);
          $User_Reporting_Username = $User_Reporting_Data ? $this->userService->DisplayUsername($Report['User_Reporting'], false, false, true) : "Unknown User";

          $Reported_User_Data = $this->userService->FetchUserData($Report['Reported_User']);
          $Reported_User_Username = $Reported_User_Data ? $this->userService->DisplayUsername($Report['Reported_User'], false, false, true) : "Unknown User";

          $Handler_Username = "N/A";
          if ($Report['Handler'] != 0) {
            $Handler_Data = $this->userService->FetchUserData($Report['Handler']);
            $Handler_Username = $Handler_Data ? $this->userService->DisplayUsername($Report['Handler'], false, false, true) : "Unknown User";
          }

          $Message_Content = Purify($Report['Message']);

          $Report_List_Text .= "
            <table class_TAG='border-gradient' style='width: 800px;'>
              <thead>
                <tr>
                  <th colspan='1' style='width: 25%;'>
                    Reported By: {$User_Reporting_Username}
                  </th>
                  <th colspan='1' style='width: 25%;'>
                    Reported User: {$Reported_User_Username}
                  </th>
                  <th colspan='1' style='width: 25%;'>
                    Handler: {$Handler_Username}
                  </th>
                  <th colspan='1' style='width: 25%;'>
                    " . date('M d, Y H:i:s', $Report['Timestamp']) . "
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan='4'>
                    {$Message_Content}
                  </td>
                </tr>
              </tbody>
              <tbody>
                <tr>
                  <td colspan='4'>
                    <button onclick='DeleteReport({$Report['ID']});'>
                      Delete Report
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          ";
        }
      }

      return $Report_List_Text;
    }

    /**
     * Delete a report.
     * @param int $Report_ID
     * @param int $actingStaffUserId
     */
    public function DeleteReport(int $Report_ID, int $actingStaffUserId)
    {
      if ( !$Report_ID )
      {
        return [
          'Success' => false,
          'Message' => 'Please select a report to delete.',
        ];
      }

      try
      {
        $this->pdo->beginTransaction();

        $Delete_Report = $this->pdo->prepare("DELETE FROM `reports` WHERE `ID` = ? LIMIT 1");
        $Delete_Report->execute([ $Report_ID ]);

        $this->staffLogService->log($actingStaffUserId, 'Deleted Report', $Report_ID);

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return [
          'Success' => false,
          'Message' => 'An error occurred while deleting this report.',
        ];
      }

      return [
        'Success' => true,
        'Message' => 'You have successfully deleted the report.',
      ];
    }
  }
