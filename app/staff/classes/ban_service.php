<?php
/**
 * Service class for handling user banning operations and fetching ban-related data.
 */
  class BanService
  {
    /** @var PDO */
    private $pdo;
    /** @var User */
    private $userService;
    /** @var StaffLogService */
    private $staffLogService;

    /**
     * Constructor for BanService.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param User $userService Instance of the User service.
     * @param StaffLogService $staffLogService Instance of the StaffLog service.
     */
    public function __construct(PDO $pdo, User $userService, StaffLogService $staffLogService)
    {
      $this->pdo = $pdo;
      $this->userService = $userService;
      $this->staffLogService = $staffLogService;
    }

    /**
     * Fetches a user by their ID or username for ban processing.
     *
     * @param string $UserValue The ID or username of the user.
     * @return array|false An associative array of user data (ID, Username) or false if not found.
     */
    public function FetchUserForBan(string $UserValue)
    {
      // This method allows fetching by either ID or Username, which is flexible for staff input.
      try
      {
        $Fetch_User = $this->pdo->prepare("
          SELECT `ID`, `Username`
          FROM `users`
          WHERE `ID` = ? OR `Username` = ?
          LIMIT 1
        ");
        $Fetch_User->execute([ $UserValue, $UserValue ]);
        $Fetch_User->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_User->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Fetches the current ban status of a specific user.
     *
     * @param int $UserID The ID of the user.
     * @return array|false An associative array of the user's ban record from `user_bans`, or false if no record exists.
     */
    public function FetchUserBanStatus(int $UserID)
    {
      try
      {
        $Fetch_User_Ban = $this->pdo->prepare("
          SELECT *
          FROM `user_bans`
          WHERE `User_ID` = ?
          LIMIT 1
        ");
        $Fetch_User_Ban->execute([ $UserID ]);
        $Fetch_User_Ban->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_User_Ban->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Ban a user from the RPG.
     * @param int $User_ID
     * @param int $RPG_Ban - Should be 1 (Ban) or 0 (Unban)
     * @param string $RPG_Ban_Reason Reason for the RPG ban.
     * @param string $RPG_Ban_Staff_Notes Staff notes regarding the RPG ban.
     * @param string|null $RPG_Ban_Until Timestamp or parsable date string for when the ban expires. Null or empty for permanent.
     * @param int $actingStaffUserId ID of the staff member performing the action.
     * @return bool True on success, false on failure.
     */
    public function RPGBanUser(
      int $User_ID,
      int $RPG_Ban,
      string $RPG_Ban_Reason = '',
      string $RPG_Ban_Staff_Notes = '',
      ?string $RPG_Ban_Until = null, // Allow null for permanent
      int $actingStaffUserId
    ): bool
    {
      if ( !$User_ID || !in_array($RPG_Ban, [0, 1], true) ) // Ensure RPG_Ban is 0 or 1
        return false;

      // Sanitize inputs that will be stored (Purify was used in AJAX, this is defense in depth)
      $RPG_Ban_Reason = Purify($RPG_Ban_Reason);
      $RPG_Ban_Staff_Notes = Purify($RPG_Ban_Staff_Notes);
      $RPG_Ban_Until = !empty($RPG_Ban_Until) ? Purify($RPG_Ban_Until) : null;


      try
      {
        $this->pdo->beginTransaction();

        $Query_Ban_User = $this->pdo->prepare("
          INSERT INTO `user_bans` (`User_ID`, `RPG_Ban`, `RPG_Ban_Reason`, `RPG_Ban_Staff_Notes`, `RPG_Ban_Until`, `RPG_Ban_Timestamp`, `Banned_By`)
          VALUES (?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE `RPG_Ban` = ?, `RPG_Ban_Reason` = ?, `RPG_Ban_Staff_Notes` = ?, `RPG_Ban_Until` = ?, `RPG_Ban_Timestamp` = ?, `Banned_By` = ?
        ");
        $Query_Ban_User->execute([
          $User_ID, $RPG_Ban, $RPG_Ban_Reason, $RPG_Ban_Staff_Notes, $RPG_Ban_Until, time(), $actingStaffUserId,
          $RPG_Ban, $RPG_Ban_Reason, $RPG_Ban_Staff_Notes, $RPG_Ban_Until, time(), $actingStaffUserId
        ]);

        $this->staffLogService->log($actingStaffUserId, 'RPG Ban', $User_ID, "Reason: {$RPG_Ban_Reason} - Staff Notes: {$RPG_Ban_Staff_Notes} - Until: {$RPG_Ban_Until}");

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
     * Ban a user from the chat.
     * @param int $User_ID
     * @param int $Chat_Ban - Should be 1 (Ban) or 0 (Unban)
     * @param string $Chat_Ban_Reason Reason for the chat ban.
     * @param string $Chat_Ban_Staff_Notes Staff notes regarding the chat ban.
     * @param string|null $Chat_Ban_Until Timestamp or parsable date string for when the ban expires. Null or empty for permanent.
     * @param int $actingStaffUserId ID of the staff member performing the action.
     * @return bool True on success, false on failure.
     */
    public function ChatBanUser(
      int $User_ID,
      int $Chat_Ban,
      string $Chat_Ban_Reason = '',
      string $Chat_Ban_Staff_Notes = '',
      ?string $Chat_Ban_Until = null, // Allow null for permanent
      int $actingStaffUserId
    ): bool
    {
      if ( !$User_ID || !in_array($Chat_Ban, [0, 1], true) ) // Ensure Chat_Ban is 0 or 1
        return false;

      // Sanitize inputs
      $Chat_Ban_Reason = Purify($Chat_Ban_Reason);
      $Chat_Ban_Staff_Notes = Purify($Chat_Ban_Staff_Notes);
      $Chat_Ban_Until = !empty($Chat_Ban_Until) ? Purify($Chat_Ban_Until) : null;

      try
      {
        $this->pdo->beginTransaction();

        $Query_Ban_User = $this->pdo->prepare("
          INSERT INTO `user_bans` (`User_ID`, `Chat_Ban`, `Chat_Ban_Reason`, `Chat_Ban_Staff_Notes`, `Chat_Ban_Until`, `Chat_Ban_Timestamp`, `Banned_By`)
          VALUES (?, ?, ?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE `Chat_Ban` = ?, `Chat_Ban_Reason` = ?, `Chat_Ban_Staff_Notes` = ?, `Chat_Ban_Until` = ?, `Chat_Ban_Timestamp` = ?, `Banned_By` = ?
        ");
        $Query_Ban_User->execute([
          $User_ID, $Chat_Ban, $Chat_Ban_Reason, $Chat_Ban_Staff_Notes, $Chat_Ban_Until, time(), $actingStaffUserId,
          $Chat_Ban, $Chat_Ban_Reason, $Chat_Ban_Staff_Notes, $Chat_Ban_Until, time(), $actingStaffUserId
        ]);

        $this->staffLogService->log($actingStaffUserId, 'Chat Ban', $User_ID, "Reason: {$Chat_Ban_Reason} - Staff Notes: {$Chat_Ban_Staff_Notes} - Until: {$Chat_Ban_Until}");

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
     * Unbans a user from both RPG and Chat by resetting their ban flags and clearing reasons/notes.
     *
     * @param int $User_ID The ID of the user to unban.
     * @param int $actingStaffUserId ID of the staff member performing the action.
     * @return bool True on success, false on failure.
     */
    public function UnbanUser(int $User_ID, int $actingStaffUserId): bool
    {
      if ( !$User_ID )
        return false;

      try
      {
        $this->pdo->beginTransaction();

        $Update_Ban_Status = $this->pdo->prepare("
          UPDATE `user_bans`
          SET `RPG_Ban` = 0, `RPG_Ban_Reason` = NULL, `RPG_Ban_Staff_Notes` = NULL, `RPG_Ban_Until` = NULL, `RPG_Ban_Timestamp` = NULL, `Banned_By` = NULL,
              `Chat_Ban` = 0, `Chat_Ban_Reason` = NULL, `Chat_Ban_Staff_Notes` = NULL, `Chat_Ban_Until` = NULL, `Chat_Ban_Timestamp` = NULL
          WHERE `User_ID` = ?
        ");
        $Update_Ban_Status->execute([ $User_ID ]);

        $this->staffLogService->log($actingStaffUserId, 'Unban', $User_ID, 'User unbanned from RPG and Chat.');

        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
     * Fetches all users who are currently RPG banned or Chat banned.
     *
     * @return array|false An array of banned user records, or false on error.
     */
    public function GetBannedUsers(): array|false
    {
      try
      {
        $Fetch_Banned_Users = $this->pdo->prepare("SELECT * FROM `user_bans` WHERE `RPG_Ban` = 1 OR `Chat_Ban` = 1 ORDER BY `User_ID` ASC");
        $Fetch_Banned_Users->execute([]);
        $Fetch_Banned_Users->setFetchMode(PDO::FETCH_ASSOC);
        return $Fetch_Banned_Users->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Generates an HTML table string displaying all banned users.
     * Ensures output is escaped for XSS protection.
     *
     * @param array $Banned_Users An array of banned user data, typically from GetBannedUsers().
     * @return string HTML string representing the table of banned users.
     */
    public function ShowBannedUsers(array $Banned_Users): string
    {
      if ( empty($Banned_Users) )
      {
        return "<tr><td colspan='7' style='padding: 7px;'>There are no users currently banned.</td></tr>";
      }

      $Ban_List_Text = "";
      foreach ( $Banned_Users as $Key => $Value )
      {
        $Username = $this->userService->DisplayUserName($Value['User_ID'], false, false, true);
        $RPG_Banned_Until = ($Value['RPG_Ban_Until'] ? $Value['RPG_Ban_Until'] : 'Permanent');
        $Chat_Banned_Until = ($Value['Chat_Ban_Until'] ? $Value['Chat_Ban_Until'] : 'Permanent');

        $Ban_List_Text .= "
          <tr>
            <td style='padding: 3px; width: 25px;'>
              <a href='javascript:void(0);' onclick=\"ShowBanInfo({$Value['User_ID']});\">
                <img src='../images/Assets/view_more.png' />
              </a>
            </td>
            <td style='padding: 3px;'>{$Username}</td> <?php // Username is from DisplayUserName, already HTML ?>
            <td style='padding: 3px;'>" . ($Value['RPG_Ban'] ? "<span style='color: red;'>Yes</span>" : "<span style='color: green;'>No</span>") . "</td>
            <td style='padding: 3px;'>" . htmlspecialchars($RPG_Banned_Until, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</td>
            <td style='padding: 3px;'>" . ($Value['Chat_Ban'] ? "<span style='color: red;'>Yes</span>" : "<span style='color: green;'>No</span>") . "</td>
            <td style='padding: 3px;'>" . htmlspecialchars($Chat_Banned_Until, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</td>
            <td style='padding: 3px; width: 25px;'>
              <a href='javascript:void(0);' onclick=\"UnbanUser({$Value['User_ID']});\">
                <img src='../images/Assets/delete.png' alt='Unban' />
              </a>
            </td>
          </tr>
          <tr id='Ban_Info_{$Value['User_ID']}' style='display: none;'>
            <td colspan='7' style='padding: 5px; text-align: left; background-color: #f9f9f9;'>
              <b>RPG Ban Reason</b>: " . htmlspecialchars($Value['RPG_Ban_Reason'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8') . "<br />
              <b>RPG Ban Staff Notes</b>: " . htmlspecialchars($Value['RPG_Ban_Staff_Notes'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8') . "<br />
              <b>Chat Ban Reason</b>: " . htmlspecialchars($Value['Chat_Ban_Reason'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8') . "<br />
              <b>Chat Ban Staff Notes</b>: " . htmlspecialchars($Value['Chat_Ban_Staff_Notes'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8') . "
            </td>
          </tr>
        ";
      }

      return $Ban_List_Text;
    }
  }
