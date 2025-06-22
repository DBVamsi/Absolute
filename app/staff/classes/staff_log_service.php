<?php
/**
 * Service class for logging staff actions.
 */
  class StaffLogService
  {
    /** @var PDO */
    private $pdo;

    /**
     * Constructor for StaffLogService.
     *
     * @param PDO $pdo The PDO database connection object.
     */
    public function __construct(PDO $pdo)
    {
      $this->pdo = $pdo;
    }

    /**
     * Logs a staff action to the database.
     *
     * @param int $staffUserId The ID of the staff member performing the action.
     * @param string $action A description of the action taken (e.g., 'Banned User', 'Deleted Report'). Max 255 chars based on typical schema.
     * @param int|null $targetUserId The ID of the user or object affected by the action, if any.
     * @param string|null $notes Additional notes, reasons, or details for the action.
     * @return bool True on successful logging, false on failure.
     */
    public function log(int $staffUserId, string $action, ?int $targetUserId = null, ?string $notes = null): bool
    {
      if ( empty($staffUserId) || empty($action) )
      {
        // Basic validation
        error_log("StaffLogService::log: Missing staffUserId or action.");
        return false;
      }

      try
      {
        $Log_Action = $this->pdo->prepare("
          INSERT INTO `staff_logs`
            (`Staff_ID`, `Action`, `Target_ID`, `Notes`, `Timestamp`)
          VALUES
            (?, ?, ?, ?, ?)
        ");
        $Log_Action->execute([
          $staffUserId,
          $action,
          $targetUserId,
          $notes,
          time()
        ]);

        return true;
      }
      catch (PDOException $e)
      {
        HandleError($e); // Assuming HandleError is a global function
        return false;
      }
    }
  }
