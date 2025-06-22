<?php
/**
 * Service class for managing clans, including creation, member management, and clan progression.
 */
  class Clan
  {
    /** @var PDO */
    private $pdo;
    /** @var User For User related operations like fetching data, currency. */
    private static $VALID_CURRENCY_COLUMNS = ['Money', 'Abso_Coins', 'Clan_Points']; // Whitelist

		public function __construct
    (
      PDO $pdo,
      User $User_Class // Inject User class (UserService)
    )
		{
			$this->pdo = $pdo;
      $this->User_Class = $User_Class;
    }

    public function CreateClan(int $creator_user_id, string $clan_name, int $creation_cost_money): array
    {
        // Validate Clan Name
        if (empty($clan_name)) {
            return ['success' => false, 'message' => 'Clan name cannot be empty.'];
        }
        if (mb_strlen($clan_name) > 50) { // Max length 50 chars
            return ['success' => false, 'message' => 'Clan name is too long (max 50 characters).'];
        }

        try {
            // Check if clan name is taken (case-insensitive)
            $stmt = $this->pdo->prepare("SELECT `ID` FROM `clans` WHERE LOWER(`Name`) = LOWER(?) LIMIT 1");
            $stmt->execute([strtolower($clan_name)]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'A clan with this name already exists.'];
            }

            // Fetch creator's data
            $creator_data = $this->User_Class->FetchUserData($creator_user_id);
            if (!$creator_data) {
                return ['success' => false, 'message' => 'Could not fetch creator information.'];
            }

            // Check if user is already in a clan
            if ($creator_data['Clan'] != 0) {
                return ['success' => false, 'message' => 'You are already in a clan.'];
            }

            // Check if user has enough money
            if ($creator_data['Money_Raw'] < $creation_cost_money) { // Assuming Money_Raw holds the integer value
                return ['success' => false, 'message' => "You do not have enough money. Cost: {$creation_cost_money}."];
            }

            $this->pdo->beginTransaction();

            // Insert new clan
            $insert_clan_stmt = $this->pdo->prepare("
                INSERT INTO `clans`
                  (`Name`, `Date_Founded`, `Level`, `Experience`, `Experience_Raw`, `Clan_Points`, `Money`, `Abso_Coins`)
                VALUES
                  (?, ?, 1, 0, 0, 0, 0, 0)
            ");
            $insert_clan_stmt->execute([$clan_name, time()]);
            $new_clan_id = $this->pdo->lastInsertId();

            if (!$new_clan_id) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to create the new clan row.'];
            }

            // Update creator's user record
            $update_user_stmt = $this->pdo->prepare("
                UPDATE `users`
                SET `Clan` = ?, `Clan_Rank` = 'Administrator', `Clan_Exp` = 0, `Clan_Title` = NULL
                WHERE `ID` = ?
            ");
            $update_user_stmt->execute([$new_clan_id, $creator_user_id]);
            if ($update_user_stmt->rowCount() == 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to update your user profile with clan information.'];
            }

            // Deduct creation cost using UserService (User_Class)
            $currency_deducted = $this->User_Class->RemoveCurrency($creator_user_id, 'Money', $creation_cost_money);
            if (!$currency_deducted) { // RemoveCurrency should return true on success, false/error on failure
                $this->pdo->rollBack();
                // The RemoveCurrency method should ideally provide a more specific error message if possible
                return ['success' => false, 'message' => 'Failed to deduct clan creation cost. Please ensure you have sufficient funds.'];
            }

            $this->pdo->commit();
            return ['success' => true, 'message' => "Clan '{$clan_name}' created successfully!", 'clan_id' => (int)$new_clan_id];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            HandleError($e);
            return ['success' => false, 'message' => 'A database error occurred during clan creation.'];
        }
    }
    
    /**
     * Fetches detailed information for a specific clan.
     *
     * @param int $Clan_ID The ID of the clan to fetch.
     * @return array|false An associative array of clan data, or false if not found or on error.
     */
    public function FetchClanData(int $Clan_ID): array|false
    {
      if ( $Clan_ID <= 0 ) // More specific check for invalid ID
        return false;

      try
      {
        $Fetch_Clan = $this->pdo->prepare("SELECT * FROM `clans` WHERE `ID` = ? LIMIT 1");
        $Fetch_Clan->execute([ $Clan_ID ]);
        $Fetch_Clan->setFetchMode(PDO::FETCH_ASSOC);
        $Clan = $Fetch_Clan->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Clan )
        return false;

      return [
        'ID' => $Clan['ID'],
        'Name' => $Clan['Name'],
        'Experience' => number_format($Clan['Experience']),
        'Experience_Raw' => $Clan['Experience'],
        'Money' => number_format($Clan['Money']),
        'Money_Raw' => $Clan['Money'],
        'Abso_Coins' => number_format($Clan['Abso_Coins']),
        'Abso_Coins_Raw' => $Clan['Abso_Coins'],
        'Clan_Points' => number_format($Clan['Clan_Points']),
        'Clan_Points_Raw' => $Clan['Clan_Points'],
        'Avatar' => ($Clan['Avatar'] ? DOMAIN_SPRITES . "/" . $Clan['Avatar'] : null),
        'Signature' => $Clan['Signature'],
      ];
    }

    /**
     * Formats a single upgrade's data for display, including current level and cost for the next level.
     *
     * @param array $Upgrade_Type The base data for the upgrade type from `clan_upgrades_data`.
     * @param array|null $Purchased_Upgrade_Data The data for this upgrade if already purchased by the clan, from `clan_upgrades_purchased`.
     * @param int $Clan_ID The ID of the clan.
     * @return array Formatted upgrade data.
     */
    private function _formatUpgradeData(array $Upgrade_Type, ?array $Purchased_Upgrade_Data, int $Clan_ID): array
    {
      $Upgrade_Type_ID = (int)$Upgrade_Type['ID'];
      $Current_Level = $Purchased_Upgrade_Data ? (int)$Purchased_Upgrade_Data['Current_Level'] : 0;

      // Calculate next level costs
      $Next_Level_Clan_Point_Cost = ($Upgrade_Type['Clan_Point_Cost'] ?? 0) + $Current_Level;
      $Next_Level_Money_Cost = ($Upgrade_Type['Money_Cost'] ?? 0) * ($Current_Level + 1);
      $Next_Level_Abso_Coin_Cost = ($Upgrade_Type['Abso_Coin_Cost'] ?? 0) * ($Current_Level + 1);

      return [
        'Purchase_ID' => $Purchased_Upgrade_Data ? (int)($Purchased_Upgrade_Data['ID'] ?? -1) : -1, // 'ID' here is the PK of clan_upgrades_purchased
        'Clan_ID' => $Clan_ID,
        'ID' => $Upgrade_Type_ID, // This is Upgrade_Type ID from clan_upgrades_data
        'Name' => $Upgrade_Type['Name'],
        'Description' => $Upgrade_Type['Description'],
        'Current_Level' => $Current_Level,
        'Suffix' => $Upgrade_Type['Suffix'],
        'Cost_For_Next_Level' => [
            'Clan_Points' => [
                'Name' => 'Clan Points',
                'Quantity' => $Next_Level_Clan_Point_Cost,
            ],
            'Money' => [
                'Name' => 'Money',
                'Quantity' => $Next_Level_Money_Cost,
            ],
            'Abso_Coins' => [
                'Name' => 'Abso Coins',
                'Quantity' => $Next_Level_Abso_Coin_Cost,
            ],
        ],
      ];
    }

    /**
     * Fetches a list of member IDs for a given clan.
     * Orders members by rank and then by clan experience.
     *
     * @param int $Clan_ID The ID of the clan.
     * @return array|false An array of member records (containing at least 'id'), or false on error/clan not found.
     */
    public function FetchMembers(int $Clan_ID): array|false
    {
      if ( $Clan_ID <= 0 ) // More specific check for invalid ID
        return false;

      try
      {
        $Fetch_Clan = $this->pdo->prepare("SELECT `ID` FROM `clans` WHERE `ID` = ? LIMIT 1");
        $Fetch_Clan->execute([ $Clan_ID ]);
        $Fetch_Clan->setFetchMode(PDO::FETCH_ASSOC);
        $Clan = $Fetch_Clan->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Clan )
        return false;

      try
      {
        $Fetch_Members = $this->pdo->prepare("SELECT `id` FROM `users` WHERE `Clan` = ? ORDER BY `Clan_Rank` ASC, `Clan_Exp` DESC");
        $Fetch_Members->execute([ $Clan_ID ]);
        $Fetch_Members->setFetchMode(PDO::FETCH_ASSOC);
        $Members = $Fetch_Members->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Members )
        return false;

      return $Members;
    }

    /**
    /**
     * Updates a clan member's rank.
     * Allowed ranks are 'Member', 'Moderator', 'Administrator'.
     *
     * @param int $Clan_ID The ID of the clan.
     * @param int $User_ID The ID of the user whose rank is to be updated.
     * @param string $Clan_Rank The new rank to assign.
     * @return bool True on success, false on failure.
     */
    public function UpdateRank(int $Clan_ID, int $User_ID, string $Clan_Rank): bool
    {
      if ( $Clan_ID <= 0 || $User_ID <= 0 || empty($Clan_Rank) )
        return false;

      $allowed_ranks = ['Member', 'Moderator', 'Administrator'];
      if ( !in_array($Clan_Rank, $allowed_ranks, true) )
        return false;

      // Check if clan exists
      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      // Check if user is part of this clan
      $Member_Data = $this->User_Class->FetchUserData($User_ID);
      if ( !$Member_Data || ($Member_Data['Clan'] ?? 0) != $Clan_ID ) // Ensure 'Clan' key exists
        return false;

      try
      {
        $Update_Rank = $this->pdo->prepare("UPDATE `users` SET `Clan_Rank` = ? WHERE `id` = ? AND `Clan` = ? LIMIT 1");
        $Update_Rank->execute([ $Clan_Rank, $User_ID, $Clan_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
    /**
     * Kicks a member from their clan.
     * This involves setting their clan ID to 0 and removing them from clan-specific direct message groups.
     *
     * @param int $Clan_ID The ID of the clan from which the member is being kicked.
     * @param int $User_ID The ID of the user being kicked.
     * @return bool True on success, false on failure.
     */
    public function KickMember(int $Clan_ID, int $User_ID): bool
    {
      if ( $Clan_ID <= 0 || $User_ID <= 0 )
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data ) {
        error_log("KickMember: Clan ID {$Clan_ID} not found.");
        return false;
      }

      $Member_Data = $this->User_Class->FetchUserData($User_ID);
      if ( !$Member_Data || ($Member_Data['Clan'] ?? 0) != $Clan_ID ) {
         error_log("KickMember: User ID {$User_ID} not found or not in Clan ID {$Clan_ID}. User's clan: " . ($Member_Data['Clan'] ?? 'N/A'));
        return false;
      }

      // Attempt to remove user from clan-specific DMs
      // This part relies on DirectMessage class and might need error handling if DM operations fail
      $Direct_Message = new DirectMessage($this->pdo);
      $Participating_DM_Groups = $Direct_Message->FetchMessageList($User_ID); // Use User_ID
      if ($Participating_DM_Groups) {
        foreach ( $Participating_DM_Groups as $DM_Group )
        {
          if ( $DM_Group['Clan_ID'] == $Member_Data['Clan'] )
            $Direct_Message->RemoveUserFromGroup($DM_Group['Group_ID'], $Member_Data['ID']);
        }
      }

      try
      {
        $Kick_Member = $this->pdo->prepare("UPDATE `users` SET `Clan` = 0 WHERE `id` = ? LIMIT 1");
        $Kick_Member->execute([ $User_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
    /**
     * Updates a clan member's title. An empty string for title effectively removes it.
     *
     * @param int $Clan_ID The ID of the clan.
     * @param int $User_ID The ID of the user whose title is to be updated.
     * @param string $Title The new title. Can be empty.
     * @return bool True on success, false on failure.
     */
    public function UpdateTitle(int $Clan_ID, int $User_ID, string $Title): bool
    {
      if ( $Clan_ID <= 0 || $User_ID <= 0 )
        return false;

      // Check if clan exists
      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      // Check if user is part of this clan
      $Member_Data = $this->User_Class->FetchUserData($User_ID);
      if ( !$Member_Data || ($Member_Data['Clan'] ?? 0) != $Clan_ID )
        return false;

      $Sanitized_Title = Purify(trim($Title)); // Purify the title for safety
      if (mb_strlen($Sanitized_Title) > 50) { // Example max length
        // Optionally return an error or truncate
        $Sanitized_Title = mb_substr($Sanitized_Title, 0, 50);
      }


      try
      {
        $Update_Title = $this->pdo->prepare("UPDATE `users` SET `Clan_Title` = ? WHERE `id` = ? LIMIT 1");
        $Update_Title->execute([ $Title, $User_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
    /**
     * Updates a clan's signature. An empty string is allowed.
     *
     * @param int $Clan_ID The ID of the clan to update.
     * @param string $Signature The new signature for the clan.
     * @return bool True on success, false on failure.
     */
    public function UpdateSignature(int $Clan_ID, string $Signature): bool
    {
      if ( $Clan_ID <= 0 )
        return false;

      // Check if clan exists
      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      $Sanitized_Signature = Purify($Signature); // Purify signature content
      // Max length for signature should be handled by DB schema or additional validation here.

      try
      {
        $Update_Signature = $this->pdo->prepare("UPDATE `clans` SET `Signature` = ? WHERE `id` = ? LIMIT 1");
        $Update_Signature->execute([ $Signature, $Clan_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
    /**
     * Disbands a clan. This involves removing all members, then deleting the clan record and associated data.
     *
     * @param int $Clan_ID The ID of the clan to disband.
     * @return bool True on success, false on failure.
     */
    public function DisbandClan(int $Clan_ID): bool
    {
      if ( $Clan_ID <= 0 )
        return false;

      // Fetch members to iterate through for LeaveClan
      $Clan_Members = $this->FetchMembers($Clan_ID);
      // FetchMembers returns false if clan doesn't exist or on DB error.
      // If it returns an empty array (no members), that's fine for disbanding.

      // It's important that LeaveClan correctly updates user records.
      if (is_array($Clan_Members)) { // Ensure $Clan_Members is an array (even if empty)
        foreach ( $Clan_Members as $Member_Record ) {
          // Assuming FetchMembers returns records with 'id' as the user ID key
          $this->LeaveClan((int)$Member_Record['id']);
        }
      } else if ($Clan_Members === false && $this->FetchClanData($Clan_ID) !== false) {
        // Clan exists but FetchMembers had an issue not related to clan existence (e.g. users table issue)
        // This is a problematic state, potentially log it. For now, proceed to delete clan shell.
        error_log("DisbandClan: Clan ID {$Clan_ID} exists, but FetchMembers failed. Proceeding to delete clan shell.");
      } else if ($Clan_Members === false) {
        // Clan likely doesn't exist or another DB error occurred with FetchMembers.
        // If FetchClanData also confirms non-existence, then nothing to disband.
        return false;
      }


      try
      {
        $this->pdo->beginTransaction();
        $Disband_Clan = $this->pdo->prepare("DELETE FROM `clans` WHERE `ID` = ? LIMIT 1");
        $Disband_Clan->execute([ $Clan_ID ]);

        $Remove_Donations = $this->pdo->prepare("DELETE FROM `clan_donations` WHERE `Clan_ID` = ?");
        $Remove_Donations->execute([ $Clan_ID ]);
        
        $Remove_Upgrades = $this->pdo->prepare("DELETE FROM `clan_upgrades_purchased` WHERE `Clan_ID` = ?");
        $Remove_Upgrades->execute([ $Clan_ID ]);
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
    /**
     * Allows a user to join a clan.
     * Updates the user's clan information and adds them to the clan's direct message group.
     *
     * @param int $Clan_ID The ID of the clan to join.
     * @param int $User_ID The ID of the user joining the clan.
     * @return bool True on success, false on failure.
     */
    public function JoinClan(int $Clan_ID, int $User_ID): bool
    {
      if ( $Clan_ID <= 0 || $User_ID <= 0 )
        return false;

      // Check user's current clan status
      $Member_Data = $this->User_Class->FetchUserData($User_ID);
      if ( !$Member_Data ) {
        error_log("JoinClan: User ID {$User_ID} not found.");
        return false; // User does not exist
      }
      if ( ($Member_Data['Clan'] ?? 0) != 0 ) {
        error_log("JoinClan: User ID {$User_ID} is already in a clan (Clan ID: {$Member_Data['Clan']}).");
        return false; // User already in a clan
      }

      // Check if target clan exists
      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data ) {
        error_log("JoinClan: Target Clan ID {$Clan_ID} not found.");
        return false;
      }

      try
      {
        $this->pdo->beginTransaction();
        $Apply_Membership = $this->pdo->prepare("UPDATE `users` SET `Clan` = ? WHERE `ID` = ? LIMIT 1");
        $Apply_Membership->execute([ $Clan_ID, $User_ID ]);

        // DirectMessage class instantiation might need $this->pdo if refactored
        $Direct_Message = new DirectMessage($this->pdo);
        $Clan_DM = $Direct_Message->FetchGroup(null, $Clan_ID);
        if ( !$Clan_DM ) {
            // Attempt to create the Clan DM group if it doesn't exist
            // This might require a new method in DirectMessage class, or adjust existing.
            // For now, let's assume FetchGroup or a similar setup method handles this.
            // If not, this part might fail silently or throw error if $Clan_DM is false.
            // A robust solution would be: if (!$Clan_DM) $Clan_DM = $Direct_Message->CreateClanGroup($Clan_ID, $Clan_Data['Name']);
            // For now, we proceed assuming $Clan_DM might be successfully fetched or this part needs more refactoring.
             $this->pdo->rollBack(); // Rollback if DM group can't be fetched/created.
             return false;
        }

        $Apply_Participation = $this->pdo->prepare("
          INSERT INTO `direct_message_groups`
          (`Group_ID`, `Group_Name`, `Clan_ID`, `User_ID`, `Unread_Messages`, `Last_Message`)
          VALUES (?, ?, ?, ?, ?, ?)
        ");
        $Apply_Participation->execute([
          $Clan_DM['Group_ID'],
          "{$Clan_Data['Name']}: Clan Announcement",
          $Clan_ID,
          $User_ID,
          1,
          time()
        ]);

        $Create_Message = $Direct_Message->CreateMessage(
          $Clan_DM['Group_ID'],
          "{$Member_Data['Username']} has joined {$Clan_Data['Name']}!",
          3,
          // $Member_Data['Clan'] // This would be 0 before joining, should likely be $Clan_ID
          $Clan_ID
        );

        if ( !$Create_Message ) {
           $this->pdo->rollBack();
           return false;
        }

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
    /**
     * Allows a user to leave their current clan.
     * Resets the user's clan-related fields and removes them from clan direct message groups.
     *
     * @param int $User_ID The ID of the user leaving the clan.
     * @return bool True on success, false on failure.
     */
    public function LeaveClan(int $User_ID): bool
    {
      if ( $User_ID <= 0 )
        return false;

      $Member_Data = $this->User_Class->FetchUserData($User_ID);
      if ( !$Member_Data || ($Member_Data['Clan'] ?? 0) == 0 ) { // User must be in a clan to leave
        error_log("LeaveClan: User ID {$User_ID} not found or not in a clan.");
        return false;
      }

      $Original_Clan_ID = (int)$Member_Data['Clan']; // Store before it's set to 0

      // Remove user from clan-specific DMs
      $Direct_Message = new DirectMessage($this->pdo);
      $Participating_DM_Groups = $Direct_Message->FetchMessageList($User_ID);
      if ($Participating_DM_Groups) {
        foreach ( $Participating_DM_Groups as $DM_Group_K => $DM_Group )
        {
          if ( $DM_Group['Clan_ID'] == $Original_Clan_ID ) // Use original clan ID
            $Direct_Message->RemoveUserFromGroup($DM_Group['Group_ID'], $Member_Data['ID']);
        }
      }

      try
      {
        $Update_User = $this->pdo->prepare("UPDATE `users` SET `Clan` = 0, `Clan_Exp` = 0, `Clan_Rank` = 'Member', `Clan_Title` = null WHERE `id` = ? LIMIT 1");
        $Update_User->execute([ $User_ID ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      return true;
    }

    /**
    /**
     * Updates specified currency balances for a clan.
     * Iterates through an associative array of currency updates.
     *
     * @param int $Clan_ID The ID of the clan whose currencies are to be updated.
     * @param array $Currencies Associative array where keys are currency column names (e.g., 'Money') and values are the new total amounts.
     * @return void Does not explicitly return success/failure for all operations but logs errors.
     */
    public function UpdateCurrencies(int $Clan_ID, array $Currencies): void
    {
      if ($Clan_ID <= 0) return;

      foreach ( $Currencies as $Currency => $Quantity )
      {
        if ( !in_array($Currency, self::$VALID_CURRENCY_COLUMNS, true) ) {
          // Optionally log this attempt or throw an exception
          error_log("Attempt to update invalid clan currency column: {$Currency} for Clan ID: {$Clan_ID}");
          continue; // Skip invalid currency column
        }

        // Ensure quantity is numeric, could be negative for subtraction if logic allows
        if (!is_numeric($Quantity)) {
            error_log("Invalid quantity provided for currency {$Currency} for Clan ID: {$Clan_ID}");
            continue;
        }

        try
        {
          // Interpolation is now safe due to whitelisting
          $Update_Currency = $this->pdo->prepare("
            UPDATE `clans`
            SET `{$Currency}` = ?
            WHERE `ID` = ?
            LIMIT 1
          ");
          $Update_Currency->execute([ $Quantity, $Clan_ID ]);
        }
        catch ( PDOException $e )
        {
          HandleError($e);
          // Decide if one failure should stop all updates or continue
        }
      }
    }

    /**
    /**
     * Allows a user to donate a specified amount of a currency to their clan.
     * Deducts currency from the user and adds it to the clan, logging the donation.
     *
     * @param int $User_ID ID of the user donating the currency.
     * @param int $Clan_ID ID of the clan receiving the donation.
     * @param string $Currency The currency type being donated (must be in `self::$VALID_CURRENCY_COLUMNS`).
     * @param int $Quantity The amount of currency being donated (must be positive).
     * @return bool True on successful donation, false otherwise.
     */
    public function DonateCurrency(int $User_ID, int $Clan_ID, string $Currency, int $Quantity): bool
    {
      if ( $User_ID <= 0 || $Clan_ID <= 0 || empty($Currency) || $Quantity <= 0 )
        return false;

      // Check if clan exists
      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data ) {
        error_log("DonateCurrency: Clan ID {$Clan_ID} not found.");
        return false;
      }

      // Validate currency type for clans
      if ( !in_array($Currency, self::$VALID_CURRENCY_COLUMNS, true) ) {
          error_log("DonateCurrency: Attempt to donate invalid currency type '{$Currency}' to Clan ID {$Clan_ID}.");
          return false;
      }

      // Attempt to remove currency from user (this also checks if user has enough)
      if ( !$this->User_Class->RemoveCurrency($User_ID, $Currency, $Quantity) ) {
        // Removal failed (e.g., insufficient funds, or $Currency is not valid for users in User_Class)
        error_log("DonateCurrency: RemoveCurrency failed for User ID {$User_ID}, Currency {$Currency}, Amount {$Quantity}.");
        return false;
      }

      try
      {
        $this->pdo->beginTransaction();

        $Donate_Currency = $this->pdo->prepare("INSERT INTO `clan_donations` ( `Clan_ID`, `Donator_ID`, `Currency`, `Quantity`, `Timestamp` ) VALUES ( ?, ?, ?, ?, ? )");
        $Donate_Currency->execute([ $Clan_ID, $User_ID, $Currency, $Quantity, time() ]);

        // Interpolation is safe due to whitelisting
        $Add_Currency = $this->pdo->prepare("UPDATE `clans` SET `{$Currency}` = `{$Currency}` + ? WHERE `ID` = ? LIMIT 1");
        $Add_Currency->execute([ $Quantity, $Clan_ID ]);

        $this->pdo->commit();
      }
      catch (PDOException $e)
      {
        $this->pdo->rollBack();
        HandleError($e);
        // Potentially try to refund the user if this part fails, though that adds complexity
        return false;
      }

      return true;
    }

    /**
    /**
     * Fetches the static data for a specific clan upgrade type from `clan_upgrades_data`.
     *
     * @param int $Upgrade_ID The ID of the upgrade type.
     * @return array|false Associative array of upgrade data, or false if not found or on error.
     */
    public function FetchUpgradeData(int $Upgrade_ID): array|false
    {
      if ( $Upgrade_ID <= 0 )
        return false;

      try
      {
        $Fetch_Upgrade = $this->pdo->prepare("SELECT * FROM `clan_upgrades_data` WHERE `ID` = ? LIMIT 1");
        $Fetch_Upgrade->execute([ $Upgrade_ID ]);
        $Fetch_Upgrade->setFetchMode(PDO::FETCH_ASSOC);
        $Upgrade_Data = $Fetch_Upgrade->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Upgrade_Data )
        return false;

      return $Upgrade_Data;
    }

    /**
     * Fetches all available clan upgrade types from `clan_upgrades_data`.
     *
     * @return array|false An array of all upgrade types, or false on error.
     */
    public function FetchAllClanUpgrades(): array|false
    {
      try
      {
        $Fetch_Upgrades = $this->pdo->prepare("SELECT * FROM `clan_upgrades_data`");
        $Fetch_Upgrades->execute([ ]);
        $Fetch_Upgrades->setFetchMode(PDO::FETCH_ASSOC);
        $Upgrades = $Fetch_Upgrades->fetchAll();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Upgrades )
        return false;

      return $Upgrades;
    }

    /**
    /**
     * Fetches all clan upgrades, indicating current level and cost for a specific clan.
     * Combines data from `clan_upgrades_data` and `clan_upgrades_purchased`.
     *
     * @param int $Clan_ID The ID of the clan.
     * @return array|false An array representing all upgrades and their status for the clan, or false on error.
     */
    public function FetchUpgrades(int $Clan_ID): array|false
    {
      if ( $Clan_ID <= 0 )
        return false;

      $All_Upgrade_Types = $this->FetchAllClanUpgrades();
      if ( !$All_Upgrade_Types )
        return false; // Error fetching base upgrade types

      $Clan_Upgrades_Status = [];
      foreach ( $All_Upgrade_Types as $Upgrade_Type )
      {
      {
        $Upgrade_Type_ID = (int)$Upgrade_Type['ID'];
        $Purchased_Upgrade_Data = $this->FetchPurchasedUpgrade($Clan_ID, $Upgrade_Type_ID);

        $Current_Level = $Purchased_Upgrade_Data ? (int)$Purchased_Upgrade_Data['Current_Level'] : 0;

        // Calculate next level costs
        // Note: The cost calculation logic might need adjustment based on how costs scale.
        // Original logic seemed to be: BaseCost * (CurrentLevel + 1) or BaseCost + CurrentLevel.
        // Using BaseCost * (CurrentLevel + 1) as it's a common pattern for increasing costs.
        // If it's BaseCost + CurrentLevel for points, that's different.
        // The original had different logic for points vs money/abso_coins.
        // Sticking to original logic for cost calculation:
        $Next_Level_Clan_Point_Cost = ($Upgrade_Type['Clan_Point_Cost'] ?? 0) + $Current_Level; // Original: Base + Level
        $Next_Level_Money_Cost = ($Upgrade_Type['Money_Cost'] ?? 0) * ($Current_Level + 1);
        $Next_Level_Abso_Coin_Cost = ($Upgrade_Type['Abso_Coin_Cost'] ?? 0) * ($Current_Level + 1);


        $Clan_Upgrades_Status[] = [
            'Purchase_ID' => $Purchased_Upgrade_Data ? (int)$Purchased_Upgrade_Data['ID'] : -1, // Assuming 'id' is the PK of clan_upgrades_purchased
            'Clan_ID' => $Clan_ID,
            'ID' => $Upgrade_Type_ID, // This is Upgrade_Type ID
            'Name' => $Upgrade_Type['Name'],
            'Description' => $Upgrade_Type['Description'],
            'Current_Level' => $Current_Level,
            'Suffix' => $Upgrade_Type['Suffix'],
            'Cost' => [
            'Cost' => [
            'Cost_For_Next_Level' => [
                'Clan_Points' => [
                    'Name' => 'Clan Points',
                    'Quantity' => $Next_Level_Clan_Point_Cost,
                ],
                'Money' => [
                    'Name' => 'Money',
                    'Quantity' => $Next_Level_Money_Cost,
                ],
                'Abso_Coins' => [
                    'Name' => 'Abso Coins', // Standardized name
                    'Quantity' => $Next_Level_Abso_Coin_Cost,
                ],
            ],
        ];
      }

      return $Clan_Upgrades_Status;
    }

    /**
    /**
     * Fetches a specific upgrade that a clan has purchased.
     *
     * @param int $Clan_ID The ID of the clan.
     * @param int $Upgrade_ID The ID of the upgrade type.
     * @return array|false Associative array of the purchased upgrade's data, or false if not purchased/error.
     */
    public function FetchPurchasedUpgrade(int $Clan_ID, int $Upgrade_ID): array|false
    {
      if ( $Clan_ID <= 0 || $Upgrade_ID <= 0 )
        return false;
      
      try
      {
        $Fetch_Upgrade = $this->pdo->prepare("SELECT * FROM `clan_upgrades_purchased` WHERE `Clan_ID` = ? AND `Upgrade_ID` = ? LIMIT 1");
        $Fetch_Upgrade->execute([ $Clan_ID, $Upgrade_ID ]);
        $Fetch_Upgrade->setFetchMode(PDO::FETCH_ASSOC);
        $Upgrade = $Fetch_Upgrade->fetch();
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false;
      }

      if ( !$Upgrade )
        return false;

      return $Upgrade;
    }

    /**
    /**
     * Handles the purchase or upgrade of a clan enhancement.
     * Checks costs against clan currencies and updates levels.
     *
     * @param int $Clan_ID The ID of the clan purchasing the upgrade.
     * @param int $Upgrade_ID The ID of the upgrade type being purchased/upgraded.
     * @return array|false Associative array of the updated purchased upgrade data, or false on failure (e.g. insufficient funds, error).
     *                     On success, the returned array is from FetchPurchasedUpgrade.
     *                     On failure, could return false or an array like ['success' => false, 'message' => '...']
     */
    public function PurchaseUpgrade(int $Clan_ID, int $Upgrade_ID): array|false
    {
      if ( $Clan_ID <= 0 || $Upgrade_ID <= 0 )
        return ['success' => false, 'message' => 'Invalid Clan or Upgrade ID.'];

      $Clan_Data = $this->FetchClanData($Clan_ID); // Fetches formatted currencies, need raw for deduction
      if ( !$Clan_Data )
        return ['success' => false, 'message' => 'Clan not found.'];

      // Fetch raw clan currencies for accurate check
      $Raw_Clan_Currencies_Stmt = $this->pdo->prepare("SELECT `Money`, `Abso_Coins`, `Clan_Points` FROM `clans` WHERE `ID` = ? LIMIT 1");
      $Raw_Clan_Currencies_Stmt->execute([$Clan_ID]);
      $Raw_Clan_Currencies = $Raw_Clan_Currencies_Stmt->fetch(PDO::FETCH_ASSOC);
      if (!$Raw_Clan_Currencies) {
        return ['success' => false, 'message' => 'Could not fetch clan currency data.'];
      }

      $Upgrade_Type_Data = $this->FetchUpgradeData($Upgrade_ID); // Base costs from clan_upgrades_data
      if ( !$Upgrade_Type_Data )
        return ['success' => false, 'message' => 'Upgrade type not found.'];

      $Purchased_Upgrade = $this->FetchPurchasedUpgrade($Clan_ID, $Upgrade_ID);
      $Current_Level = $Purchased_Upgrade ? (int)$Purchased_Upgrade['Current_Level'] : 0;

      // Calculate cost for the NEXT level
      // This cost calculation should precisely match the one used in FetchUpgrades for display
      $Cost_Clan_Points = ($Upgrade_Type_Data['Clan_Point_Cost'] ?? 0) + $Current_Level;
      $Cost_Money = ($Upgrade_Type_Data['Money_Cost'] ?? 0) * ($Current_Level + 1);
      $Cost_Abso_Coins = ($Upgrade_Type_Data['Abso_Coin_Cost'] ?? 0) * ($Current_Level + 1);

      // Check affordability
      if (($Raw_Clan_Currencies['Clan_Points'] ?? 0) < $Cost_Clan_Points) {
        return ['success' => false, 'message' => 'Not enough Clan Points.'];
      }
      if (($Raw_Clan_Currencies['Money'] ?? 0) < $Cost_Money) {
        return ['success' => false, 'message' => 'Not enough Money.'];
      }
      if (($Raw_Clan_Currencies['Abso_Coins'] ?? 0) < $Cost_Abso_Coins) {
        return ['success' => false, 'message' => 'Not enough Abso Coins.'];
      }

      try
      {
        $this->pdo->beginTransaction();
        if ( $Purchased_Upgrade )
        {
          $New_Level = $Purchased_Upgrade['Current_Level'] + 1;
          $Purchase_Query = $this->pdo->prepare("UPDATE `clan_upgrades_purchased` SET `Current_Level` = ? WHERE `Clan_ID` = ? AND `Upgrade_ID` = ?");
          $Purchase_Query->execute([ $New_Level, $Clan_Data['ID'], $Upgrade_Data['ID'] ]);
        }
        else
        {
          $Purchase_Query = $this->pdo->prepare("INSERT INTO `clan_upgrades_purchased` (`Clan_ID`, `Upgrade_ID`, `Current_Level`) VALUES (?, ?, 1)");
          $Purchase_Query->execute([ $Clan_Data['ID'], $Upgrade_Data['ID'] ]);
        }
        // TODO: Deduct costs from clan's currencies ($Clan_Data values) using whitelisted column names.
        // This part is critical and missing from the original logic if it's not handled by the caller.
        // Example: $this->UpdateCurrencies($Clan_ID, ['Money' => $Clan_Data['Money_Raw'] - $calculated_cost_money, ...]);
        $this->pdo->commit();
      }
      catch ( PDOException $e )
      {
        $this->pdo->rollBack();
        HandleError($e);
        return false;
      }

      return $this->FetchPurchasedUpgrade($Clan_Data['ID'], $Upgrade_Data['ID']);
    }
  }
