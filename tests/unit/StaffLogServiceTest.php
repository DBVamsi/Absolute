<?php

use PHPUnit\Framework\TestCase;

// Assuming bootstrap.php handles including StaffLogService
// If StaffLogService.php is not found by the autoloader, uncommenting a direct require might be needed:
// require_once dirname(dirname(__DIR__)) . '/app/staff/classes/staff_log_service.php';

class StaffLogServiceTest extends TestCase
{
    private $pdoMock;
    private $stmtMock;
    private $staffLogService;

    protected function setUp(): void
    {
        // Create mocks for PDO and PDOStatement
        // Note: In PHPUnit, type hinting for properties like $pdoMock (e.g., private PDO $pdoMock;)
        // became common with PHP 7.4+. For compatibility or if not using strict types,
        // the direct property declaration is fine. Test methods will still use createMock.
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMock = $this->createMock(PDOStatement::class);

        // Instantiate the service with the mock PDO
        $this->staffLogService = new StaffLogService($this->pdoMock);
    }

    public function testLogActionSuccessfullyInsertsData()
    {
        $staffUserId = 1;
        $action = 'Test Action';
        $targetUserId = 100; // Can be null
        $notes = 'Test notes for action.';
        $expectedTimestamp = time(); // For approximate comparison

        // Define the expected SQL (using stringContains for flexibility with whitespace)
        // Actual query from StaffLogService:
        // INSERT INTO `staff_logs` (`Staff_ID`, `Action`, `Target_ID`, `Notes`, `Timestamp`) VALUES (?, ?, ?, ?, ?)
        $expectedSqlPartial = 'INSERT INTO `staff_logs`';

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains($expectedSqlPartial))
            ->willReturn($this->stmtMock);

        // Expect PDOStatement::execute to be called once with the correct parameters
        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) use ($staffUserId, $action, $targetUserId, $notes, $expectedTimestamp) {
                if (!is_array($params) || count($params) !== 5) return false;
                if ($params[0] !== $staffUserId) return false;
                if ($params[1] !== $action) return false;
                if ($params[2] !== $targetUserId) return false;
                if ($params[4] !== $notes) return false; // Notes is the 5th param (index 4)
                // Allow a small time difference for timestamp (index 3), e.g., up to 2 seconds
                if (abs($params[3] - $expectedTimestamp) > 2) return false;
                return true;
            }))
            ->willReturn(true); // Simulate successful execution

        // Call the method to be tested
        $result = $this->staffLogService->log($staffUserId, $action, $targetUserId, $notes);

        // Assert that the log method returns true on success
        $this->assertTrue($result, "StaffLogService::log() should return true on successful database insert.");
    }

    public function testLogActionWithNullTargetAndNotes()
    {
        $staffUserId = 2;
        $action = 'System Maintenance';
        $targetUserId = null;
        $notes = null;
        $expectedTimestamp = time();

        $expectedSqlPartial = 'INSERT INTO `staff_logs`';

        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains($expectedSqlPartial))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->with($this->callback(function ($params) use ($staffUserId, $action, $targetUserId, $notes, $expectedTimestamp) {
                if (!is_array($params) || count($params) !== 5) return false;
                if ($params[0] !== $staffUserId) return false;
                if ($params[1] !== $action) return false;
                if ($params[2] !== $targetUserId) return false; // Expecting null
                if (abs($params[3] - $expectedTimestamp) > 2) return false;
                if ($params[4] !== $notes) return false; // Expecting null
                return true;
            }))
            ->willReturn(true);

        $result = $this->staffLogService->log($staffUserId, $action, $targetUserId, $notes);
        $this->assertTrue($result);
    }

    public function testLogActionFailsIfStaffIdOrActionIsEmpty()
    {
        // Test with empty staffUserId
        $result1 = $this->staffLogService->log(0, 'Test Action'); // Assuming 0 is invalid for staffUserId
        $this->assertFalse($result1, "log() should return false if staffUserId is empty or invalid.");

        // Test with empty action
        $result2 = $this->staffLogService->log(1, '');
        $this->assertFalse($result2, "log() should return false if action string is empty.");
    }

    public function testLogActionHandlesPDOExceptionOnPrepare()
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO `staff_logs`'))
            ->willThrowException(new PDOException("Test PDO Prepare Exception"));

        // The HandleError function is global and might echo or log,
        // but for this test, we just care that log() returns false.
        // If HandleError exits, this test might behave unexpectedly in some environments.
        // For now, assuming HandleError logs and allows execution to continue (or return false).

        $result = $this->staffLogService->log(1, 'Action causing prepare error', 100, 'Notes');
        $this->assertFalse($result, "log() should return false when PDO::prepare() throws an exception.");
    }

    public function testLogActionHandlesPDOExceptionOnExecute()
    {
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('INSERT INTO `staff_logs`'))
            ->willReturn($this->stmtMock);

        $this->stmtMock->expects($this->once())
            ->method('execute')
            ->willThrowException(new PDOException("Test PDO Execute Exception"));

        $result = $this->staffLogService->log(1, 'Action causing execute error', 100, 'Notes');
        $this->assertFalse($result, "log() should return false when PDOStatement::execute() throws an exception.");
    }
}
?>
