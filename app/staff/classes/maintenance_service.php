<?php
/**
 * Service class for managing site and page maintenance modes.
 */
  class MaintenanceService
  {
    /** @var PDO */
    private $pdo;
    /** @var StaffLogService */
    private $staffLogService;

    /**
     * Constructor for MaintenanceService.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param StaffLogService $staffLogService Instance of the StaffLog service for logging actions.
     */
    public function __construct(PDO $pdo, StaffLogService $staffLogService)
    {
      $this->pdo = $pdo;
      $this->staffLogService = $staffLogService;
    }

    /**
     * Checks if a page exists by its ID.
     *
     * @param int $Page_ID The ID of the page to check.
     * @return array|false The page data as an associative array if found (ID, Name, Maintenance status), otherwise false.
     */
    public function CheckPageExistence(int $Page_ID)
    {
      if ($Page_ID <= 0) return false; // Basic validation

      try
      {
        $Fetch_Page = $this->pdo->prepare("SELECT `ID`, `Name`, `Maintenance` FROM `pages` WHERE `ID` = ? LIMIT 1");
        $Fetch_Page->execute([ $Page_ID ]);
        $Fetch_Page->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_Page->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Generates an HTML table string displaying all pages and their maintenance statuses.
     * Output is intended for staff panel display. Page names are HTML-escaped.
     *
     * @return string HTML string representing the table of pages, or an error message string.
     */
    public function ShowMaintenanceTable(): string
    {
      try
      {
        $Fetch_Pages = $this->pdo->prepare("SELECT `ID`, `Name`, `Maintenance` FROM `pages` ORDER BY `Name` ASC");
        $Fetch_Pages->execute([]);
        $Fetch_Pages->setFetchMode(PDO::FETCH_ASSOC);
        $Pages = $Fetch_Pages->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return "Error fetching page data.";
      }

      $Page_List = '';
      if ( !$Pages || count($Pages) === 0 )
      {
        $Page_List = "
          <tbody>
            <tr>
              <td colspan='3' style='padding: 7px;'>
                No pages have been found.
              </td>
            </tr>
          </tbody>
        ";
      }
      else
      {
        foreach ( $Pages as $Page )
        {
          $Page_Name_Escaped = htmlspecialchars($Page['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
          $Page_List .= "
            <tr>
              <td colspan='1'>
                {$Page_Name_Escaped}
              </td>
              <td colspan='1' id='page-{$Page['ID']}-status'>
                " . ($Page['Maintenance'] === 'yes' ? 'Enabled' : 'Disabled') . "
              </td>
              <td colspan='1'>
                <button onclick=\"Toggle_Page_Maintenance({$Page['ID']});\">
                  Toggle
                </button>
              </td>
            </tr>
          ";
        }
      }

      return "
        <table class='border-gradient' style='width: 600px;'>
          <thead>
            <tr>
              <th colspan='1'>Page Name</th>
              <th colspan='1'>Status</th>
              <th colspan='1'>Toggle</th>
            </tr>
          </thead>
          <tbody>
            {$Page_List}
          </tbody>
        </table>
      ";
    }

    /**
     * Toggles the maintenance status of a specific page and logs the action.
     *
     * @param int $Page_ID The ID of the page to toggle.
     * @param int $actingStaffUserId The ID of the staff member performing the action.
     * @return array An associative array with 'Success' (bool), 'Message' (string), and 'New_Status' (string 'Enabled'/'Disabled').
     */
    public function TogglePageMaintenance(int $Page_ID, int $actingStaffUserId): array
    {
      if ( !$Page_ID || $Page_ID <= 0 ) // Basic validation
      {
        return [
          'Success' => false,
          'Message' => 'Invalid page ID.',
        ];
      }

      $Page = $this->CheckPageExistence($Page_ID);

      if ( !$Page )
      {
        return [
          'Success' => false,
          'Message' => 'Page not found.',
        ];
      }

      $New_Status = ($Page['Maintenance'] === 'yes' ? 'no' : 'yes');

      try
      {
        $this->pdo->beginTransaction();
        $Update_Page = $this->pdo->prepare("UPDATE `pages` SET `Maintenance` = ? WHERE `ID` = ? LIMIT 1");
        $Update_Page->execute([ $New_Status, $Page_ID ]);

        $this->staffLogService->log($actingStaffUserId, "Toggled {$Page['Name']} Maintenance", $Page_ID, "New Status: {$New_Status}");

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return [
          'Success' => false,
          'Message' => 'Error updating page maintenance status.',
        ];
      }

      $Page_Name_Escaped_For_Message = htmlspecialchars($Page['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      return [
        'Success' => true,
        'Message' => "Maintenance mode for page '{$Page_Name_Escaped_For_Message}' has been " . ($New_Status === 'yes' ? 'enabled' : 'disabled') . ".",
        'New_Status' => ($New_Status === 'yes' ? 'Enabled' : 'Disabled'),
      ];
    }
  }
