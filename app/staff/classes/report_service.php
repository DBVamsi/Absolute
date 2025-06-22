<?php
/**
 * Service class for managing user reports and staff actions related to them.
 */
  class ReportService
  {
    /** @var PDO */
    private $pdo;
    /** @var User */
    private $userService;
    /** @var StaffLogService */
    private $staffLogService;

    /**
     * Constructor for ReportService.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param User $userService Instance of the User service for fetching user data.
     * @param StaffLogService $staffLogService Instance of the StaffLog service for logging actions.
     */
    public function __construct(PDO $pdo, User $userService, StaffLogService $staffLogService)
    {
      $this->pdo = $pdo;
      $this->userService = $userService;
      $this->staffLogService = $staffLogService;
    }

    /**
     * Fetches a specific report by its ID to check for its existence.
     *
     * @param int $Report_ID The ID of the report to check.
     * @return array|false The report data as an associative array if found, otherwise false.
     */
    public function CheckReportExistence(int $Report_ID)
    {
      if ($Report_ID <= 0) return false; // Basic validation

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
     * Fetches all currently active reports, ordered by timestamp.
     *
     * @return array|false An array of active report data, or false on database error.
     */
    public function GetActiveReports(): array|false
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
     * Generates an HTML string displaying all active reports.
     * Output is intended to be used within a JSON response for AJAX calls.
     * Usernames are pre-formatted HTML from UserService::DisplayUsername.
     * Report messages are Purify'd. Dates are htmlspecialchar'd.
     *
     * @param array $Active_Reports An array of active report data, typically from GetActiveReports().
     * @return string HTML string representing the table of active reports, or a message if none exist.
     */
    public function ShowActiveReports(array $Active_Reports): string
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

          $Message_Content = Purify($Report['Message']); // Assuming Purify makes it safe for HTML echo
          $Formatted_Timestamp = htmlspecialchars(date('M d, Y H:i:s', $Report['Timestamp']), ENT_QUOTES | ENT_HTML5, 'UTF-8');

          $Report_List_Text .= "
            <table class='border-gradient' style='width: 800px;'>
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
                    {$Formatted_Timestamp}
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
     * Deletes a specific report from the database and logs the action.
     *
     * @param int $Report_ID The ID of the report to delete.
     * @param int $actingStaffUserId The ID of the staff member performing the deletion.
     * @return array An associative array with 'Success' (bool) and 'Message' (string).
     */
    public function DeleteReport(int $Report_ID, int $actingStaffUserId): array
    {
      if ( !$Report_ID || $Report_ID <= 0) // Basic validation
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
