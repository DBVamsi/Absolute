<?php
  class BanService
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
     * Fetch a user by ID or Username for ban processing.
     * @param string $UserValue
     * @return array|false
     */
    public function FetchUserForBan(string $UserValue)
    {
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
     * Fetch a user's ban status.
     * @param int $UserID
     * @return array|false
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
     * @param string $RPG_Ban_Reason
     * @param string $RPG_Ban_Staff_Notes
     * @param string $RPG_Ban_Until
     * @param int $actingStaffUserId
     */
    public function RPGBanUser(
      int $User_ID,
      int $RPG_Ban,
      string $RPG_Ban_Reason = '',
      string $RPG_Ban_Staff_Notes = '',
      string $RPG_Ban_Until = '',
      int $actingStaffUserId
    )
    {
      if ( !$User_ID || !isset($RPG_Ban) )
        return false;

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
     * @param string $Chat_Ban_Reason
     * @param string $Chat_Ban_Staff_Notes
     * @param string $Chat_Ban_Until
     * @param int $actingStaffUserId
     */
    public function ChatBanUser(
      int $User_ID,
      int $Chat_Ban,
      string $Chat_Ban_Reason = '',
      string $Chat_Ban_Staff_Notes = '',
      string $Chat_Ban_Until = '',
      int $actingStaffUserId
    )
    {
      if ( !$User_ID || !isset($Chat_Ban) )
        return false;

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
     * Unban a user from both RPG and Chat.
     * @param int $User_ID
     * @param int $actingStaffUserId
     */
    public function UnbanUser(int $User_ID, int $actingStaffUserId)
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
     * Fetch all banned users.
     */
    public function GetBannedUsers()
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
     * Display all banned users.
     * @param array $Banned_Users - Array of banned user data, typically from GetBannedUsers()
     */
    public function ShowBannedUsers(array $Banned_Users)
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
            <td style='padding: 3px;'>{$Username}</td>
            <td style='padding: 3px;'>" . ($Value['RPG_Ban'] ? "<span style='color: red;'>Yes</span>" : "<span style='color: green;'>No</span>") . "</td>
            <td style='padding: 3px;'>{$RPG_Banned_Until}</td>
            <td style='padding: 3px;'>" . ($Value['Chat_Ban'] ? "<span style='color: red;'>Yes</span>" : "<span style='color: green;'>No</span>") . "</td>
            <td style='padding: 3px;'>{$Chat_Banned_Until}</td>
            <td style='padding: 3px; width: 25px;'>
              <a href='javascript:void(0);' onclick=\"UnbanUser({$Value['User_ID']});\">
                <img src='../images/Assets/delete.png' />
              </a>
            </td>
          </tr>
          <tr id='Ban_Info_{$Value['User_ID']}' style='display: none;'>
            <td colspan='7'>
              <b>RPG Ban Reason</b>: {$Value['RPG_Ban_Reason']}<br />
              <b>RPG Ban Staff Notes</b>: {$Value['RPG_Ban_Staff_Notes']}<br />
              <b>Chat Ban Reason</b>: {$Value['Chat_Ban_Reason']}<br />
              <b>Chat Ban Staff Notes</b>: {$Value['Chat_Ban_Staff_Notes']}
            </td>
          </tr>
        ";
      }

      return $Ban_List_Text;
    }
  }
