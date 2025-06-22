<?php
/**
 * Service class for handling direct messages, groups, and participants.
 */
  class DirectMessage
  {
    /** @var PDO */
    private $pdo;
    /** @var User */
    private $userService;
    /** @var int Stores the last fetched or generated Group_ID to potentially create new sequential group IDs. */
    public $Last_Group_ID = 0;
    // $User property which stored global $User_Data is removed. User context needs to be passed to methods.

    /**
     * Constructor for DirectMessage service.
     *
     * @param PDO $pdo The PDO database connection object.
     * @param User $userService Instance of the User service.
     */
    public function __construct(PDO $pdo, User $userService)
    {
      $this->pdo = $pdo;
      $this->userService = $userService;
    }

    /**
     * Fetches a list of direct message groups a user has participated in.
     *
     * @param int $User_ID The ID of the user whose message list to fetch.
     * @return array|false An array of message group data, or false if none or on error.
     */
    public function FetchMessageList(int $User_ID): array|false
    {
      if ($User_ID <= 0) {
        return false;
      }
      
      try
      {
        $Fetch_Messages = $this->pdo->prepare("SELECT * FROM `direct_message_groups` WHERE `User_ID` = ? GROUP BY `Group_ID` ORDER BY `Last_Message` DESC");
        $Fetch_Messages->execute([ $User_ID ]);
        // Default fetch mode is PDO::FETCH_ASSOC from session.php
        $Messages = $Fetch_Messages->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      if ( !$Messages )
        return false;

      return $Messages;
    }

    /**
     * Fetch the data of a specific direct message group.
     */
    /**
     * Fetches data for a specific direct message group, either by Group_ID or Clan_ID.
     *
     * @param int|null $Group_ID The ID of the message group.
     * @param int|null $Clan_ID The ID of the clan (for clan-wide messages).
     * @return array|false Group data as an associative array, or false if not found/error.
     */
    public function FetchGroup(?int $Group_ID = null, ?int $Clan_ID = null): array|false
    {
      if ( ($Group_ID === null || $Group_ID <= 0) && ($Clan_ID === null || $Clan_ID <= 0) ) {
        return false;
      }

      try
      {
        if ( $Clan_ID && $Clan_ID > 0 )
        {
          $Check_Conversation = $this->pdo->prepare("SELECT * FROM `direct_message_groups` WHERE `Clan_ID` = ? LIMIT 1");
          $Check_Conversation->execute([ $Clan_ID ]);
        }
        else // if ( $Group_ID && $Group_ID > 0 ) implicitly
        {
          $Check_Conversation = $this->pdo->prepare("SELECT * FROM `direct_message_groups` WHERE `Group_ID` = ? LIMIT 1");
          $Check_Conversation->execute([ $Group_ID ]);
        }

        $Conversation = $Check_Conversation->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      if ( !$Conversation )
        return false;

      return $Conversation;
    }

    /**
     * Fetch an array of messages from a specific direct message.
     * @param $Group_ID - ID of a given direct message.
     * @param $User_ID - ID of the user attempting to load a direct message.
     */
    /**
     * Fetches all messages within a specific direct message group for a given user.
     * Ensures the user is a participant of the group.
     *
     * @param int $Group_ID ID of the message group.
     * @param int $User_ID ID of the user attempting to load the messages.
     * @return array|false An array of messages, or false if not found, not a participant, or on error.
     */
    public function FetchMessage(int $Group_ID, int $User_ID): array|false
    {
      if ( $Group_ID <= 0 || $User_ID <= 0 )
        return false;
      
      $Conversation = $this->FetchGroup($Group_ID);
      if ( !$Conversation ) {
        error_log("FetchMessage: Group ID {$Group_ID} not found.");
        return false;
      }

      if ( !$this->IsParticipating($Conversation['Group_ID'], $User_ID) ) {
        error_log("FetchMessage: User ID {$User_ID} is not a participant in Group ID {$Group_ID}.");
        return false;
      }

      try
      {
        $Fetch_Messages = $this->pdo->prepare("SELECT * FROM `direct_messages` WHERE `Group_ID` = ? ORDER BY `Timestamp` ASC");
        $Fetch_Messages->execute([ $Group_ID ]);
        $Messages = $Fetch_Messages->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      if ( !$Messages )
        return false;

      return $Messages;
    }

    /**
     * Update the group direct message read db value when the user reads a DM.
     */
    /**
     * Marks messages in a group as read for a specific user (sets Unread_Messages to 0).
     *
     * @param int $Group_ID The ID of the message group.
     * @param int $User_ID The ID of the user for whom messages are to be marked as read.
     * @return bool True on success, false on failure or if user is not a participant.
     */
    public function ReadDirectMessage(int $Group_ID, int $User_ID): bool
    {
      if ( $Group_ID <= 0 || $User_ID <= 0 )
        return false;
      
      // FetchGroup also implicitly checks if the group exists.
      $Conversation = $this->FetchGroup($Group_ID);
      if ( !$Conversation ) {
        error_log("ReadDirectMessage: Group ID {$Group_ID} not found.");
        return false;
      }

      if ( !$this->IsParticipating($Conversation['Group_ID'], $User_ID) ) {
         error_log("ReadDirectMessage: User ID {$User_ID} is not a participant in Group ID {$Group_ID}.");
        return false;
      }

      try
      {
        // This query updates a row and doesn't typically return rows to fetch with PDO::FETCH_ASSOC.
        // We should check rowCount() for success.
        $Update_Read_Status = $this->pdo->prepare("UPDATE `direct_message_groups` SET `Unread_Messages` = 0 WHERE `Group_ID` = ? AND `User_ID` = ? LIMIT 1");
        $Update_Read_Status->execute([ $Group_ID, $User_ID ]);

        return $Update_Read_Status->rowCount() > 0;
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Update the unread messages count for all users in a direct message.
     */
    /**
     * Updates the unread message count for all participants in a group except the sender.
     *
     * @param int $Group_ID The ID of the message group.
     * @param int $Sender_User_ID The ID of the user who sent the message (to be excluded from count update).
     * @return bool True on success, false on failure.
     */
    public function UpdateReadCount(int $Group_ID, int $Sender_User_ID): bool
    {
      if ( $Group_ID <= 0 || $Sender_User_ID <= 0 )
        return false;
      
      // Check if the group exists, not strictly necessary if called after CreateMessage, but good for standalone use.
      $Conversation = $this->FetchGroup($Group_ID);
      if ( !$Conversation ) {
        error_log("UpdateReadCount: Group ID {$Group_ID} not found.");
        return false;
      }

      // It's assumed the Sender_User_ID is a valid participant if they just sent a message.
      // No explicit IsParticipating check for sender here, but could be added.

      // The logic for $Message_Count based on FetchMessageList() seems complex and potentially incorrect
      // for simply incrementing unread counts. Usually, it's just +1 for each new message.
      // Reverting to a simple +1 increment.
      $Increment_Value = 1;

      try
      {
        $Update_Counts = $this->pdo->prepare("
          UPDATE `direct_message_groups`
          SET `Unread_Messages` = `Unread_Messages` + ?, `Last_Message` = UNIX_TIMESTAMP()
          WHERE `Group_ID` = ? AND `User_ID` != ?
        ");
        // Update last message timestamp for the group for all participants as well
        $Update_Counts->execute([ $Increment_Value, $Group_ID, $Sender_User_ID ]);

        // rowCount() might not be reliable for UPDATE on all drivers/versions for number of rows affected.
        // If no rows were updated (e.g., group with only the sender), it's not necessarily an error.
        return true;
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }
    }

    /**
     * Check to see if a user is a participant in a given direct message.
     * @param $Group_ID - ID of a given direct message.
     * @param $User_ID - ID of the user that we're checking to see if they're a participant in the message.
     */
    /**
     * Checks if a user is a participant in a given direct message group.
     *
     * @param int $Group_ID The ID of the message group.
     * @param int $User_ID The ID of the user.
     * @return bool True if the user is a participant, false otherwise or on error.
     */
    public function IsParticipating(int $Group_ID, int $User_ID): bool
    {
      if ( $Group_ID <= 0 || $User_ID <= 0 )
        return false;

      try
      {
        $Check_Participation = $this->pdo->prepare("SELECT `ID` FROM `direct_message_groups` WHERE `Group_ID` = ? AND `User_ID` = ? LIMIT 1");
        $Check_Participation->execute([ $Group_ID, $User_ID ]);
        return $Check_Participation->fetch() !== false;
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Safer to assume not participating on error
      }
    }

    /**
     * Fetch the ID of the last direct message group.
     */
    /**
     * Fetches the next available Group_ID for creating a new direct message group.
     * This method aims to find the max current Group_ID and increment it.
     * Note: This approach might have race condition issues in high concurrency.
     * A database sequence or AUTO_INCREMENT on Group_ID (if Group_ID is not shared across users in `direct_message_groups` table but refers to a common `conversations` table) would be more robust.
     * For now, assuming `Group_ID` in `direct_message_groups` is the shared identifier for a conversation.
     *
     * @return int The next available Group_ID.
     */
    public function FetchGroupID(): int
    {
      try
      {
        // Find the current maximum Group_ID
        $Fetch_Last_Group_ID = $this->pdo->query("SELECT MAX(`Group_ID`) FROM `direct_message_groups`");
        $Last_Group_ID_Val = $Fetch_Last_Group_ID ? (int)$Fetch_Last_Group_ID->fetchColumn() : 0;

        $this->Last_Group_ID = $Last_Group_ID_Val + 1;
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        // Fallback in case of error, though this could lead to ID collision if DB is partially working
        $this->Last_Group_ID = time(); // Using timestamp as a less ideal fallback to get a unique-ish ID
      }

      return $this->Last_Group_ID;
    }

    /**
     * Compose a new direct message.
     */
    /**
     * Composes and creates a new direct message group and its initial message.
     *
     * @param string $Group_Title Title for the new message group.
     * @param string $Message_Text The initial message content.
     * @param array $Included_Users Array of user IDs to include in the group. Assumes current user is the first in this array or implicitly included.
     * @param int|null $Clan_ID Optional. If this is a clan-wide message, the ID of the clan.
     * @param int $Creator_User_ID The User ID of the person creating the message.
     * @return int|false The new Group_ID on success, or false on failure.
     */
    public function ComposeMessage(string $Group_Title, string $Message_Text, array $Included_Users, ?int $Clan_ID = null, int $Creator_User_ID): int|false
    {
      if (empty($Message_Text) || empty($Included_Users) || $Creator_User_ID <= 0) {
        error_log("ComposeMessage: Missing required parameters.");
        return false;
      }

      $Group_Title_Sanitized = empty(trim($Group_Title)) ? 'Untitled Group' : Purify(trim($Group_Title));
      $Message_Text_Sanitized = Purify($Message_Text); // Message is purified before DB storage
      $Clan_ID_Validated = ($Clan_ID !== null && $Clan_ID > 0) ? (int)$Clan_ID : null;

      // Determine Group_ID: use existing for clan announcement, or new for other DMs.
      $Group_ID = null;
      if ($Clan_ID_Validated !== null) {
        $Existing_Clan_Group = $this->FetchGroup(null, $Clan_ID_Validated);
        if ($Existing_Clan_Group) {
          $Group_ID = (int)$Existing_Clan_Group['Group_ID'];
        }
      }

      if ($Group_ID === null) { // If not a pre-existing clan group, or no clan ID, generate new group ID
        $Group_ID = $this->FetchGroupID();
      }

      // Add all included users (including creator) to the direct_message_groups table for this new/existing Group_ID
      // Ensure creator is part of the group.
      $all_participants_ids = [];
      foreach ($Included_Users as $User_Entry) { // Assuming $Included_Users might be like [['User_ID' => id1], ['User_ID' => id2]]
          $participant_id = isset($User_Entry['User_ID']) ? (int)$User_Entry['User_ID'] : (is_numeric($User_Entry) ? (int)$User_Entry : 0);
          if ($participant_id > 0 && !in_array($participant_id, $all_participants_ids)) {
              $all_participants_ids[] = $participant_id;
          }
      }
      // Ensure creator is in the list if not already
      if (!in_array($Creator_User_ID, $all_participants_ids)) {
          $all_participants_ids[] = $Creator_User_ID;
      }


      foreach ( $all_participants_ids as $User_ID_To_Add )
      {
        // Check if user exists before adding to group
        $Fetched_User = $this->userService->FetchUserData($User_ID_To_Add);
        if ( !$Fetched_User ) {
          error_log("ComposeMessage: User ID {$User_ID_To_Add} not found. Skipping for group {$Group_ID}.");
          continue; // Skip this user
        }

        // Create entry in direct_message_groups for this user and group ID
        // The CreateMessageGroup method needs $Creator_User_ID to set Unread_Messages correctly.
        if ( !$this->CreateMessageGroup($Group_ID, $Group_Title_Sanitized, $Message_Text_Sanitized, $User_ID_To_Add, $Clan_ID_Validated, $Creator_User_ID) ) {
          // If one fails, we might want to roll back all previous CreateMessageGroup for this $Group_ID
          // For now, log and continue, or return false.
          error_log("ComposeMessage: Failed to create message group entry for User ID {$User_ID_To_Add} and Group ID {$Group_ID}.");
          // return false; // Optionally, fail the whole operation
        }
      }
      
      // Create the initial message in the direct_messages table
      if ( !$this->CreateMessage($Group_ID, $Message_Text_Sanitized, $Creator_User_ID, $Clan_ID_Validated) ) {
        error_log("ComposeMessage: Failed to create initial message for Group ID {$Group_ID}.");
        return false;
      }

      return $Group_ID;
    }

    /**
     * Insert a message group into the database.
     */
    /**
     * Creates an entry in `direct_message_groups` linking a user to a message group.
     * This table seems to store participation records for each user in a group.
     *
     * @param int $Group_ID The ID of the message group.
     * @param string $Group_Title The title of the group.
     * @param string $Message_Text The initial message text (used for Last_Message, but seems redundant if CreateMessage updates it).
     * @param int $User_ID The ID of the user being added to the group.
     * @param int|null $Clan_ID Optional. Clan ID if it's a clan message group.
     * @param int $Creator_User_ID The ID of the user who initiated the message/group creation (to set Unread_Messages).
     * @return bool True on success, false on failure.
     */
    public function CreateMessageGroup(int $Group_ID, string $Group_Title, string $Message_Text, int $User_ID, ?int $Clan_ID = null, int $Creator_User_ID): bool
    {
      // Parameters $Group_Title, $Message_Text are already sanitized by ComposeMessage
      // $Group_ID, $User_ID, $Clan_ID are validated integers
      if ( $Group_ID <= 0 || $User_ID <= 0 || $Creator_User_ID <= 0) {
         error_log("CreateMessageGroup: Invalid Group_ID, User_ID, or Creator_User_ID.");
        return false;
      }
      if ($Clan_ID !== null && $Clan_ID <= 0) { // Allow null, but if provided, must be > 0
         error_log("CreateMessageGroup: Invalid Clan_ID.");
        return false;
      }


      // $Fetch_User = $this->userService->FetchUserData($User_ID); // Already done in ComposeMessage for validation
      // if ( !$Fetch_User ) return false;

      // Set unread unless the user being added is the one creating the message.
      $Unread_Messages = ($Creator_User_ID === $User_ID) ? 0 : 1;

      try
      {
        $Create_Message_Group = $PDO->prepare("
          INSERT INTO `direct_message_groups`
          (`Group_ID`, `Group_Name`, `Clan_ID`, `User_ID`, `Unread_Messages`, `Last_Message`)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        $Create_Message_Group->execute([
          $Group_ID,
          $Group_Title,
          $Clan_ID,
          $User_ID,
          $Unread_Messages,
          time()
        ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      return true;
    }

    /**
     * Insert a message into the appropriate message group in the database.
     */
    /**
     * Creates a new message within a direct message group.
     * Updates the read count for other participants.
     *
     * @param int $Group_ID The ID of the message group.
     * @param string $Message_Text The content of the message (should be pre-sanitized if coming from user input).
     * @param int $Sender_User_ID The ID of the user sending the message.
     * @param int|null $Clan_ID Optional. Clan ID if it's a clan message.
     * @return bool True on success, false on failure.
     */
    public function CreateMessage(int $Group_ID, string $Message_Text, int $Sender_User_ID, ?int $Clan_ID = null): bool
    {
      // $Message_Text is assumed to be already sanitized (e.g., by ComposeMessage or an AJAX handler)
      // $Group_ID, $Sender_User_ID, $Clan_ID are validated integers
      if ( empty($Message_Text) || $Group_ID <= 0 || $Sender_User_ID <= 0 ) {
        error_log("CreateMessage: Invalid parameters.");
        return false;
      }
      if ($Clan_ID !== null && $Clan_ID <= 0) {
        error_log("CreateMessage: Invalid Clan_ID.");
        return false;
      }


      // Check if group exists (FetchGroup also implicitly does this)
      if ( !$this->FetchGroup($Group_ID, $Clan_ID) ) { // Pass Clan_ID if available to ensure correct group context
        error_log("CreateMessage: Group ID {$Group_ID} (Clan ID: {$Clan_ID}) not found.");
        return false;
      }

      // Check if sender is a participant (IsParticipating should be sufficient)
      if ( !$this->IsParticipating($Group_ID, $Sender_User_ID) ) {
        error_log("CreateMessage: Sender User ID {$Sender_User_ID} is not a participant in Group ID {$Group_ID}.");
        return false;
      }

      // Increment unread messages for other participants and update Last_Message for all
      $this->UpdateReadCount($Group_ID, $Sender_User_ID);

      try
      {
        $Create_Message = $PDO->prepare("
          INSERT INTO `direct_messages`
          (`Group_ID`, `Clan_ID`, `User_ID`, `Message`, `Timestamp`)
          VALUES (?, ?, ?, ?, ?)
        ");
        $Create_Message->execute([
          $Group_ID,
          $Clan_ID,
          $User_ID,
          $Message_Text,
          time()
        ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      return true;
    }

    /**
     * Remove a user from a specified direct message group.
     */
    /**
     * Removes a user from a specified direct message group.
     * Posts a system message indicating the user has been removed.
     *
     * @param int $Group_ID The ID of the message group.
     * @param int $User_ID_To_Remove The ID of the user to remove from the group.
     * @return bool True on success, false on failure.
     */
    public function RemoveUserFromGroup(int $Group_ID, int $User_ID_To_Remove): bool
    {
      if ( $Group_ID <= 0 || $User_ID_To_Remove <= 0 )
        return false;

      // Check if group exists
      $Group_Data = $this->FetchGroup($Group_ID);
      if ( !$Group_Data ) {
         error_log("RemoveUserFromGroup: Group ID {$Group_ID} not found.");
        return false;
      }

      // Check if user is actually in the group
      if ( !$this->IsParticipating($Group_ID, $User_ID_To_Remove) ) {
        error_log("RemoveUserFromGroup: User ID {$User_ID_To_Remove} is not a participant in Group ID {$Group_ID}.");
        return false; // Not in group, nothing to remove
      }

      $Removed_User_Data = $this->userService->FetchUserData($User_ID_To_Remove);
      $Removed_Username = $Removed_User_Data ? $Removed_User_Data['Username'] : "User #{$User_ID_To_Remove}";

      // Post a system message about removal (User ID 3 is often a system/notification user)
      // The Clan_ID for this system message might be $Group_Data['Clan_ID'] if available
      $System_Message_Sender_ID = SYSTEM_USER_ID; // Assuming SYSTEM_USER_ID constant exists (e.g., 0 or a specific bot ID)
      if (!defined('SYSTEM_USER_ID')) {
          define('SYSTEM_USER_ID', 0); // Define if not exists, placeholder for actual system user ID
      }

      $this->CreateMessage(
        $Group_ID,
        htmlspecialchars($Removed_Username, ENT_QUOTES | ENT_HTML5, 'UTF-8') . " has been removed from the group.",
        SYSTEM_USER_ID,
        $Group_Data['Clan_ID'] ?? null
      );
      // Not checking return of CreateMessage here, as primary goal is removal.

      try
      {
        $RemoveFromGroup = $PDO->prepare("DELETE FROM `direct_message_groups` WHERE `Group_ID` = ? AND `User_ID` = ?");
        $RemoveFromGroup->execute([ $Group_ID, $User_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
      }

      return true;
    }
  }
  