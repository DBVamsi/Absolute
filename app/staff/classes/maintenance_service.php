<?php
  class MaintenanceService
  {
    private $pdo;
    private $staffLogService;

    public function __construct(PDO $pdo, StaffLogService $staffLogService)
    {
      $this->pdo = $pdo;
      $this->staffLogService = $staffLogService;
    }

    /**
     * Check if a page exists by its ID.
     * @param int $Page_ID
     * @return array|false
     */
    public function CheckPageExistence(int $Page_ID)
    {
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
     * Show the maintenance table.
     */
    public function ShowMaintenanceTable()
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
          $Page_List .= "
            <tr>
              <td colspan='1'>
                {$Page['Name']}
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
     * Toggle the maintenance status of a page.
     * @param int $Page_ID
     * @param int $actingStaffUserId
     */
    public function TogglePageMaintenance(int $Page_ID, int $actingStaffUserId)
    {
      if ( !$Page_ID )
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

      return [
        'Success' => true,
        'Message' => "Maintenance mode for page '{$Page['Name']}' has been " . ($New_Status === 'yes' ? 'enabled' : 'disabled') . ".",
        'New_Status' => ($New_Status === 'yes' ? 'Enabled' : 'Disabled'),
      ];
    }
  }
