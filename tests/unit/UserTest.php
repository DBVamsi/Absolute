<?php

use PHPUnit\Framework\TestCase;

// Assuming bootstrap.php handles including User class and its dependencies (like PokemonService for type hints if any)
// require_once dirname(dirname(__DIR__)) . '/app/core/classes/user.php';

class UserTest extends TestCase
{
    private $pdoMock;
    private $stmtMockUser;
    private $stmtMockBan;
    private $stmtMockRoster; // For FetchRoster's DB calls
    private $stmtMockPassword; // For AuthenticateUser password fetch
    private $stmtMockUpdate; // For generic updates like RemoveCurrency, UpdateLastLogin
    private $user;

    protected function setUp(): void
    {
        $this->pdoMock = $this->createMock(PDO::class);
        $this->stmtMockUser = $this->createMock(PDOStatement::class);
        $this->stmtMockBan = $this->createMock(PDOStatement::class);
        $this->stmtMockRoster = $this->createMock(PDOStatement::class);
        $this->stmtMockPassword = $this->createMock(PDOStatement::class);
        $this->stmtMockUpdate = $this->createMock(PDOStatement::class);

        $this->user = new User($this->pdoMock);
    }

    public function testFetchUserDataSuccessfullyRetrievesData()
    {
        $userId = 1;
        $sampleUserData = [
            'ID' => $userId, 'Username' => 'TestUser', 'Clan' => 0, 'Money' => 1000, 'Abso_Coins' => 50,
            'TrainerExp' => 1200, 'Clan_Exp' => 0, 'Clan_Rank' => 'Member', 'Clan_Title' => null,
            'Avatar' => '/avatars/default.png', 'Playtime' => 3600, 'Roster' => '1,2,3', /* etc. */
            // Fill with all columns from users table + user_currency table for the join
            'Map_ID' => 1, 'Map_X' => 10, 'Map_Y' => 10, 'Map_Z' => 0, 'Map_Steps_To_Encounter' => 10, 'Map_Experience' => 0,
            'Gender' => 'Male', 'Status' => 'Okay', 'Staff_Message' => null, 'Is_Staff' => 0, 'Rank' => 'Member',
            'Mastery_Points_Total' => 0, 'Mastery_Points_Used' => 0, 'Last_Active' => time(), 'Date_Registered' => time() - 86400,
            'Last_Page' => 'index.php', 'Auth_Code' => 'testauth', 'Theme' => 'default', 'Battle_Theme' => 'default'
        ];
        $sampleBanData = ['User_ID' => $userId, 'RPG_Ban' => 0, 'Chat_Ban' => 0];
        $sampleRosterData = [ // Data as FetchRoster's internal query would return
            ['ID' => 1, 'Pokedex_ID' => 1, /* other pokemon fields */],
            ['ID' => 2, 'Pokedex_ID' => 4, /* other pokemon fields */],
        ];

        // Mock for FetchUserData's main query
        $this->pdoMock->expects($this->atLeastOnce()) // Called for user, then for roster user check
            ->method('prepare')
            ->with($this->logicalOr(
                $this->stringContains('SELECT * FROM `users` INNER JOIN `user_currency`'), // Main user data
                $this->stringContains('SELECT * FROM `user_bans` WHERE `User_ID` = ?'),      // Ban data
                $this->stringContains('SELECT `ID` FROM `users` WHERE `ID` = ? LIMIT 1'),    // FetchRoster's user check
                $this->stringContains("SELECT * FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Roster'") // FetchRoster's pokemon query
            ))
            ->willReturnCallback(function ($query) {
                if (strpos($query, 'SELECT * FROM `users` INNER JOIN `user_currency`') !== false) {
                    return $this->stmtMockUser;
                }
                if (strpos($query, 'SELECT * FROM `user_bans`') !== false) {
                    return $this->stmtMockBan;
                }
                if (strpos($query, "SELECT `ID` FROM `users` WHERE `ID` = ? LIMIT 1") !== false) {
                    // This is for FetchRoster's internal user check
                    $stmtRosterUserCheckMock = $this->createMock(PDOStatement::class);
                    $stmtRosterUserCheckMock->expects($this->once())->method('execute')->with([$this->equalTo(1)])->willReturn(true);
                    $stmtRosterUserCheckMock->expects($this->once())->method('fetch')->willReturn(['ID' => 1]); // Simulate user exists
                    return $stmtRosterUserCheckMock;
                }
                if (strpos($query, "SELECT * FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Roster'") !== false) {
                    return $this->stmtMockRoster;
                }
                return $this->createMock(PDOStatement::class); // Default for other unexpected prepares
            });

        $this->stmtMockUser->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn($sampleUserData);

        $this->stmtMockBan->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockBan->expects($this->once())->method('fetch')->willReturn($sampleBanData);

        $this->stmtMockRoster->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockRoster->expects($this->once())->method('fetchAll')->willReturn($sampleRosterData);

        $result = $this->user->FetchUserData($userId);

        $this->assertIsArray($result);
        $this->assertEquals($userId, $result['ID']);
        $this->assertEquals('TestUser', $result['Username']);
        $this->assertEquals($sampleRosterData, $result['Roster']); // Check if roster data is passed through
        $this->assertFalse($result['RPG_Ban']);
    }

    public function testFetchUserDataReturnsFalseForNonExistentUser()
    {
        $userId = 999;
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM `users` INNER JOIN `user_currency`'))
            ->willReturn($this->stmtMockUser);

        $this->stmtMockUser->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn(false);

        // Ban query should not be reached if user fetch fails
        $this->stmtMockBan->expects($this->never())->method('execute');


        $result = $this->user->FetchUserData($userId);
        $this->assertFalse($result);
    }

    public function testCheckUsernameExistsReturnsTrueWhenUsernamePresent()
    {
        $username = 'testuser';
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT `ID` FROM `users` WHERE LOWER(`Username`) = LOWER(?)'))
            ->willReturn($this->stmtMockUser);

        $this->stmtMockUser->expects($this->once())->method('execute')->with([strtolower($username)])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn(['ID' => 1]); // Simulate user found

        $this->assertTrue($this->user->CheckUsernameExists($username));
    }

    public function testCheckUsernameExistsReturnsFalseWhenUsernameNotPresent()
    {
        $username = 'nosuchuser';
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT `ID` FROM `users` WHERE LOWER(`Username`) = LOWER(?)'))
            ->willReturn($this->stmtMockUser);

        $this->stmtMockUser->expects($this->once())->method('execute')->with([strtolower($username)])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn(false); // Simulate user not found

        $this->assertFalse($this->user->CheckUsernameExists($username));
    }

    public function testAuthenticateUserSuccess()
    {
        $username = 'testuser';
        $password = 'password123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userId = 1;

        // Mock for fetching user by username
        $this->pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->with($this->logicalOr(
                $this->stringContains('SELECT `ID`, `Username` FROM `users` WHERE LOWER(`Username`) = LOWER(?)'),
                $this->stringContains('SELECT `Password` FROM `user_passwords` WHERE `ID` = ?')
            ))
            ->willReturnCallback(function($query) {
                if (strpos($query, 'SELECT `ID`, `Username`') !== false) return $this->stmtMockUser;
                if (strpos($query, 'SELECT `Password`') !== false) return $this->stmtMockPassword;
                return $this->createMock(PDOStatement::class);
            });

        $this->stmtMockUser->expects($this->once())->method('execute')->with([strtolower($username)])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn(['ID' => $userId, 'Username' => $username]);

        $this->stmtMockPassword->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockPassword->expects($this->once())->method('fetchColumn')->willReturn($hashedPassword);

        $result = $this->user->AuthenticateUser($username, $password);

        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals($username, $result['username']);
    }

    public function testAuthenticateUserFailureInvalidPassword()
    {
        $username = 'testuser';
        $correctPassword = 'password123';
        $wrongPassword = 'wrongpassword';
        $hashedPassword = password_hash($correctPassword, PASSWORD_DEFAULT);
        $userId = 1;

         $this->pdoMock->expects($this->atLeastOnce())
            ->method('prepare')
            ->with($this->logicalOr(
                $this->stringContains('SELECT `ID`, `Username` FROM `users` WHERE LOWER(`Username`) = LOWER(?)'),
                $this->stringContains('SELECT `Password` FROM `user_passwords` WHERE `ID` = ?')
            ))
            ->willReturnCallback(function($query) {
                if (strpos($query, 'SELECT `ID`, `Username`') !== false) return $this->stmtMockUser;
                if (strpos($query, 'SELECT `Password`') !== false) return $this->stmtMockPassword;
                 return $this->createMock(PDOStatement::class);
            });

        $this->stmtMockUser->expects($this->once())->method('execute')->with([strtolower($username)])->willReturn(true);
        $this->stmtMockUser->expects($this->once())->method('fetch')->willReturn(['ID' => $userId, 'Username' => $username]);

        $this->stmtMockPassword->expects($this->once())->method('execute')->with([$userId])->willReturn(true);
        $this->stmtMockPassword->expects($this->once())->method('fetchColumn')->willReturn($hashedPassword);

        $result = $this->user->AuthenticateUser($username, $wrongPassword);

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid username or password.', $result['message']);
    }

    public function testRemoveCurrencySuccess()
    {
        $userId = 1;
        $currency = 'Money';
        $amount = 100;

        $this->pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains("UPDATE `user_currency` SET `{$currency}` = `{$currency}` - ? WHERE `ID` = ? AND `{$currency}` >= ?"))
            ->willReturn($this->stmtMockUpdate);

        $this->stmtMockUpdate->expects($this->once())->method('execute')->with([$amount, $userId, $amount])->willReturn(true);
        $this->stmtMockUpdate->expects($this->once())->method('rowCount')->willReturn(1); // Simulate one row affected
        $this->pdoMock->expects($this->once())->method('commit')->willReturn(true);

        $this->assertTrue($this->user->RemoveCurrency($userId, $currency, $amount));
    }

    public function testRemoveCurrencyFailureInsufficientFunds()
    {
        $userId = 1;
        $currency = 'Money';
        $amount = 100;

        $this->pdoMock->expects($this->once())->method('beginTransaction')->willReturn(true);
        $this->pdoMock->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains("UPDATE `user_currency` SET `{$currency}` = `{$currency}` - ? WHERE `ID` = ? AND `{$currency}` >= ?"))
            ->willReturn($this->stmtMockUpdate);

        $this->stmtMockUpdate->expects($this->once())->method('execute')->with([$amount, $userId, $amount])->willReturn(true);
        $this->stmtMockUpdate->expects($this->once())->method('rowCount')->willReturn(0); // Simulate no rows affected (insufficient funds)
        $this->pdoMock->expects($this->once())->method('rollBack')->willReturn(true);
        $this->pdoMock->expects($this->never())->method('commit');

        $this->assertFalse($this->user->RemoveCurrency($userId, $currency, $amount));
    }

}
?>
