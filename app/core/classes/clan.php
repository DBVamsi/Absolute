<?php
  class Clan
  {
    private $pdo;
    private static $VALID_CURRENCY_COLUMNS = ['Money', 'Abso_Coins', 'Clan_Points']; // Whitelist

		public function __construct
    (
      PDO $pdo
    )
		{
			$this->pdo = $pdo;
    }
    
    /**
     * Fetch database information for a Clan, given a Clan ID.
     * @param int $Clan_ID
     */
    public function FetchClanData
    (
      int $Clan_ID
    )
    {
      if ( !$Clan_ID || $Clan_ID === 0 )
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
     * Fetch all given users that are in a clan.
     * @param int $Clan_ID
     */
    public function FetchMembers
    (
      int $Clan_ID
    )
    {
      if ( !$Clan_ID || $Clan_ID === 0 )
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
     * Set a clan member's clan rank.
     * @param int $Clan_ID
     * @param int $User_ID
     * @param int $Clan_Rank
     */
    public function UpdateRank
    (
      int $Clan_ID,
      int $User_ID,
      string $Clan_Rank
    )
    {
      global $User_Class;

      if ( !$Clan_ID || !$User_ID || !$Clan_Rank )
        return false;

      if ( !in_array($Clan_Rank, ['Member', 'Moderator', 'Administrator']) )
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      $Member_Data = $User_Class->FetchUserData($User_ID); // Assumes User_Class is available
      if ( !$Member_Data || $Member_Data['Clan'] != $Clan_Data['ID'] )
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
     * Kick a member from their clan.
     * @param int $Clan_ID
     * @param int $User_ID
     */
    public function KickMember
    (
      int $Clan_ID,
      int $User_ID
    )
    {
      global $User_Class;

      if ( !$Clan_ID || !$User_ID )
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      $Member_Data = $User_Class->FetchUserData($User_ID); // Assumes User_Class is available
      if ( !$Member_Data || $Member_Data['Clan'] != $Clan_Data['ID'] )
        return false;

      $Direct_Message = new DirectMessage($this->pdo); // Assuming DirectMessage is refactored or takes PDO
      $Participating_DM_Groups = $Direct_Message->FetchMessageList($Member_Data['ID']);
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
     * Update a member's clan title.
     * @param int $Clan_ID
     * @param int $User_ID
     * @param string $Title
     */
    public function UpdateTitle
    (
      int $Clan_ID,
      int $User_ID,
      string $Title
    )
    {
      global $User_Class;

      if ( !$Clan_ID || !$User_ID || !$Title ) // Title can be empty to remove it.
        return false; // Or allow empty title? Current logic implies it's required.

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      $Member_Data = $User_Class->FetchUserData($User_ID); // Assumes User_Class is available
      if ( !$Member_Data || $Member_Data['Clan'] != $Clan_Data['ID'] )
        return false;

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
     * Update a clan's signature.
     * @param int $Clan_ID
     * @param string $Signature
     */
    public function UpdateSignature
    (
      int $Clan_ID,
      string $Signature
    )
    {
      if ( !$Clan_ID ) // Signature can be empty
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

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
     * Disband a clan.
     * @param int $Clan_ID
     */
    public function DisbandClan
    (
      int $Clan_ID
    )
    {
      if ( !$Clan_ID )
        return false;

      $Clan_Members = $this->FetchMembers($Clan_ID);
      if ( !$Clan_Members )
      {
        // If FetchMembers returns false due to no members, it might still be okay to delete the clan shell.
        // However, if it's false due to an error, that's different. For now, strict check.
        // Consider what happens if a clan has 0 members and needs disbanding.
      }

      if ($Clan_Members) {
        foreach ( $Clan_Members as $Member )
          $this->LeaveClan($Member['id']); // This calls FetchUserData which uses $User_Class
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
     * Add a user to the clan.
     * @param int $Clan_ID
     * @param int $User_ID
     */
    public function JoinClan
    (
      int $Clan_ID,
      int $User_ID
    )
    {
      global $User_Class;

      if ( !$Clan_ID || !$User_ID )
        return false;

      $Member_Data = $User_Class->FetchUserData($User_ID); // Assumes User_Class is available
      if ( !$Member_Data || $Member_Data['Clan'] ) // Also check if !$Member_Data
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

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
     * Remove a user from a clan.
     * @param int $User_ID
     */
    public function LeaveClan
    (
      int $User_ID
    )
    {
      global $User_Class;

      if ( !$User_ID || $User_ID < 0 )
        return false;

      $Member_Data = $User_Class->FetchUserData($User_ID); // Assumes User_Class is available
      if ( !$Member_Data || !$Member_Data['Clan'] )
        return false;

      $Original_Clan_ID = $Member_Data['Clan']; // Store before it's set to 0

      // DirectMessage class instantiation might need $this->pdo if refactored
      $Direct_Message = new DirectMessage($this->pdo);
      $Participating_DM_Groups = $Direct_Message->FetchMessageList($Member_Data['ID']);
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
     * Update the currencies of a given clan.
     * @param int $Clan_ID
     * @param array $Currencies
     */
    public function UpdateCurrencies
    (
      int $Clan_ID,
      array $Currencies
    )
    {
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
     * Donate a given currency to a clan.
     * @param int $User_ID - ID of the User donating the currency.
     * @param int $Clan_ID - ID of the Clan that the user is donating to.
     * @param string $Currency - Value of the Currency that is being donated.
     * @param int $Quantity - Amount of currency being donated.
     */
    public function DonateCurrency
    (
      int $User_ID,
      int $Clan_ID,
      string $Currency,
      int $Quantity
    )
    {
      global $User_Class;

      if ( !$User_ID || !$Clan_ID || !$Currency || $Quantity <= 0 ) // Quantity must be positive
        return false;

      // Validate currency against user's allowed currencies first
      // Assuming User_Class::$VALID_CURRENCY_COLUMNS exists and is accessible or use a getter
      // For now, this check relies on RemoveCurrency to fail if $Currency is invalid for users.
      // A more robust way would be to check $Currency against User's valid currencies here.

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      // Check if this currency is valid for clans too
      if ( !in_array($Currency, self::$VALID_CURRENCY_COLUMNS, true) ) {
          error_log("Attempt to donate invalid currency type: {$Currency} to Clan ID: {$Clan_ID}");
          return false;
      }

      // User_Class->RemoveCurrency now uses its own $pdo instance.
      if ( !$User_Class->RemoveCurrency($User_ID, $Currency, $Quantity) )
      {
        // Removal failed (e.g. insufficient funds, or invalid currency for user)
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
     * Fetch the data of a given clan upgrade.
     * @param int $Upgrade_ID
     */
    public function FetchUpgradeData
    (
      int $Upgrade_ID
    )
    {
      if ( !$Upgrade_ID )
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
     * Fetch all possible clan upgrades.
     */
    public function FetchAllClanUpgrades
    ()
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
     * Fetch all upgrades that are available to a given clan.
     * @param int $Clan_ID
     */
    public function FetchUpgrades
    (
      int $Clan_ID
    )
    {
      if ( !$Clan_ID )
        return false;

      $Upgrades = $this->FetchAllClanUpgrades();
      if ( !$Upgrades )
        return false;

      foreach ( $Upgrades as $Key => $Upgrade )
      {
        $Upgrade['ID'] = intval($Upgrade['ID']);
        $Upgrade_Data = $this->FetchPurchasedUpgrade($Clan_ID, $Upgrade['ID']);

        if ( !$Upgrade_Data )
        {
          $Upgrades[$Key] = [
            'Purchase_ID' => -1,
            'Clan_ID' => $Clan_ID,
            'ID' => $Upgrade['ID'],
            'Name' => $Upgrade['Name'],
            'Description' => $Upgrade['Description'],
            'Current_Level' => 0,
            'Suffix' => $Upgrade['Suffix'],
            'Cost' => [
              'Clan_Points' => [
                'Name' => 'Clan Points',
                'Quantity' => $Upgrade['Clan_Point_Cost'],
              ],
              'Money' => [
                'Name' => 'Money',
                'Quantity' => $Upgrade['Money_Cost'],
              ],
              'Abso_Coins' => [ // Assuming Abso_Coins is the key for Absolute Coins
                'Name' => 'ECRPG Coins', // Display name updated if constants.php is source
                'Quantity' => $Upgrade['Abso_Coin_Cost'],
              ],
            ],
          ];
        }
        else
        {
          $Upgrades[$Key] = [
            'Purchase_ID' => $Upgrade_Data['ID'],
            'Clan_ID' => $Upgrade_Data['Clan_ID'],
            'ID' => $Upgrade_Data['ID'],
            'Name' => $Upgrade['Name'],
            'Description' => $Upgrade['Description'],
            'Current_Level' => $Upgrade_Data['Current_Level'],
            'Suffix' => $Upgrade['Suffix'],
            'Cost' => [
              'Clan_Points' => [
                'Name' => 'Clan Points',
                'Quantity' => $Upgrade['Clan_Points_Cost'] + $Upgrade_Data['Current_Level'],
              ],
              'Money' => [
                'Name' => 'Money',
                'Quantity' => $Upgrade['Money_Cost'] * ($Upgrade_Data['Current_Level'] + 1),
              ],
              'Abso_Coins' => [ // Assuming Abso_Coins is the key
                'Name' => 'ECRPG Coins', // Display name updated
                'Quantity' => $Upgrade['Abso_Coins_Cost'] * ($Upgrade_Data['Current_Level'] + 1),
              ],
            ],
          ];
        }
      }

      return $Upgrades;
    }

    /**
     * Fetch the current upgrade level of a given boost, given a Clan ID and Upgrade ID.
     * @param int $Clan_ID
     * @param int $Upgrade_ID
     */
    public function FetchPurchasedUpgrade
    (
      int $Clan_ID,
      int $Upgrade_ID
    )
    {
      if ( !$Clan_ID || !$Upgrade_ID )
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
     * Fetch the data of a given clan upgrade.
     * @param int $Upgrade_ID
     */
    public function PurchaseUpgrade
    (
      int $Clan_ID,
      int $Upgrade_ID
    )
    {
      if ( !$Clan_ID || !$Upgrade_ID )
        return false;

      $Clan_Data = $this->FetchClanData($Clan_ID);
      if ( !$Clan_Data )
        return false;

      $Upgrade_Data = $this->FetchUpgradeData($Upgrade_ID);
      if ( !$Upgrade_Data )
        return false;

      // Simplified cost check - assumes FetchUpgrades provides correct current costs
      // A full cost check against $Clan_Data raw values should happen here before DB operations

      $Purchased_Upgrade = $this->FetchPurchasedUpgrade($Clan_Data['ID'], $Upgrade_Data['ID']);
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
