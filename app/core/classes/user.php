<?php
	class User
	{
		private $pdo;
		private static $VALID_CURRENCY_COLUMNS = ['Money', 'Abso_Coins']; // Whitelist

		/**
		 * Construct and initialize the class.
		 */
		public function __construct(PDO $pdo)
		{
			$this->pdo = $pdo;
		}

		/**
		 * Fetch the complete data set of a specific user via their `users` DB ID.
		 */
		public function FetchUserData($User_Query)
		{
			if ( !$User_Query )
				return false;

			$User_Query = Purify($User_Query);

			try
			{
				$Fetch_User = $this->pdo->prepare("
					SELECT *
					FROM `users`
					  INNER JOIN `user_currency`
            ON `users`.`ID` = `user_currency`.`ID`
					WHERE `users`.`ID` = ?
					LIMIT 1
				");
				$Fetch_User->execute([ $User_Query ]);
				$Fetch_User->setFetchMode(PDO::FETCH_ASSOC);
				$User = $Fetch_User->fetch();

        $Check_User_Ban = $this->pdo->prepare("
          SELECT *
          FROM `user_bans`
          WHERE `User_ID` = ?
          LIMIT 1
        ");
        $Check_User_Ban->execute([
          $User_Query
        ]);
        $Check_User_Ban->setFetchMode(PDO::FETCH_ASSOC);
        $User_Ban = $Check_User_Ban->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return false; // Added to prevent further processing on error
			}

			if ( !$User )
				return false;

			$Roster = $this->FetchRoster($User['ID']);
			if ( !$Roster )
				$Roster = null;

			// This check is redundant as it's covered by !$User above.
			// if ( !isset($User) || !$User )
			//	return false;

			if ( isset($User_Ban['RPG_Ban']) && $User_Ban['RPG_Ban'] )
				$Banned_RPG = true;
			else
				$Banned_RPG = false;

			if ( isset($User_Ban['Chat_Ban']) && $User_Ban['Chat_Ban'] )
				$Banned_Chat = true;
			else
				$Banned_Chat = false;

			if ( $User['Playtime'] == 0 )
				$Playtime = "None";
			elseif ( $User['Playtime'] <= 59 )
				$Playtime = $User['Playtime']." Second(s)";
			elseif ( $User['Playtime'] >= 60 && $User['Playtime'] <= 3599 )
				$Playtime = floor($User['Playtime'] / 60)." Minute(s)";
			elseif ( $User['Playtime'] >= 3600 && $User['Playtime'] <= 86399 )
				$Playtime = round($User['Playtime'] / 3600, 1)." Hour(s)";
			else
				$Playtime = round($User['Playtime'] / 86400, 2)." Day(s)";

			return [
				'ID' => $User['ID'],
				'Username' => $User['Username'],
				'Roster' => $Roster,
				'Roster_Hash' => $User['Roster'],
				'Avatar' => DOMAIN_SPRITES . $User['Avatar'],
				'RPG_Ban' => $Banned_RPG,
				'Chat_Ban' => $Banned_Chat,
				'Money' => $User['Money'],
        'Abso_Coins' => $User['Abso_Coins'],
        'Trainer_Level' => number_format(FetchLevel($User['TrainerExp'], 'Trainer')),
        'Trainer_Level_Raw' => FetchLevel($User['TrainerExp'], 'Trainer'),
        'Trainer_Exp' => number_format($User['TrainerExp']),
				'Trainer_Exp_Raw' => $User['TrainerExp'],
				'Clan' => $User['Clan'],
				'Clan_Exp' => number_format($User['Clan_Exp']),
				'Clan_Exp_Raw' => $User['Clan_Exp'],
				'Clan_Rank' => $User['Clan_Rank'],
				'Clan_Title' => $User['Clan_Title'],
        'Map_Experience' => $User['Map_Experience'],
        'Map_ID' => $User['Map_ID'],
        'Map_Position' => [
          'Map_X' => $User['Map_X'],
          'Map_Y' => $User['Map_Y'],
          'Map_Z' => $User['Map_Z'],
        ],
        'Map_Steps_To_Encounter' => $User['Map_Steps_To_Encounter'],
        'Gender' => $User['Gender'],
				'Status' => $User['Status'],
				'Staff_Message' => $User['Staff_Message'],
				'Is_Staff' => $User['Is_Staff'],
				'Rank' => $User['Rank'],
				'Mastery_Points_Total' => $User['Mastery_Points_Total'],
				'Mastery_Points_Used' => $User['Mastery_Points_Used'],
				'Last_Active' => $User['Last_Active'],
				'Date_Registered' => $User['Date_Registered'],
				'Last_Page' => $User['Last_Page'],
				'Playtime' => $Playtime,
				'Auth_Code' => $User['Auth_Code'],
				'Theme' => $User['Theme'],
				'Battle_Theme' => $User['Battle_Theme'],
			];
		}

		/**
		 * Fetch a given user's roster.
		 * @param int $User_ID
		 */
		public function FetchRoster
		(
			int $User_ID
		)
		{
			if ( !$User_ID )
				return false;

			try
			{
				$User_Check = $this->pdo->prepare("SELECT `ID` FROM `users` WHERE `ID` = ? LIMIT 1");
				$User_Check->execute([ $User_ID ]);
				$User_Check->setFetchMode(PDO::FETCH_ASSOC);
				$User = $User_Check->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return false; // Added
			}

			if ( !$User )
				return false;

			try
			{
				$Fetch_Roster = $this->pdo->prepare("SELECT * FROM `pokemon` WHERE `Owner_Current` = ? AND `Location` = 'Roster' ORDER BY `Slot` ASC LIMIT 6");
				$Fetch_Roster->execute([ $User_ID ]);
				$Fetch_Roster->setFetchMode(PDO::FETCH_ASSOC);
				$Roster = $Fetch_Roster->fetchAll();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return false; // Added
			}

			return $Roster;
		}

		/**
		 * Remove some of the user's currency.
		 * @param int $User_ID - The id of the user that we're updating.
		 * @param string $Currency - The DB field name of the currency that we're updating.
		 * @param int $Amount - The amount of currency that we're removing.
		 */
		public function RemoveCurrency(int $User_ID, string $Currency, int $Amount)
		{
			if ( !$User_ID || !$Currency || $Amount <= 0 ) // Amount should be positive
				return false;

      // Check against whitelist
      if ( !in_array($Currency, self::$VALID_CURRENCY_COLUMNS, true) ) {
        // Optionally log this attempt or throw an exception
        error_log("Attempt to update invalid currency column: {$Currency} for User ID: {$User_ID}");
        return false;
      }

			try
			{
        $this->pdo->beginTransaction();

				// Interpolation is now safe due to whitelisting
				$Update_Query = $this->pdo->prepare("UPDATE `user_currency` SET `{$Currency}` = `{$Currency}` - ? WHERE `ID` = ? AND `{$Currency}` >= ? LIMIT 1");
				$Update_Query->execute([ $Amount, $User_ID, $Amount ]); // Ensure user has enough

        if ($Update_Query->rowCount() == 0) {
            $this->pdo->rollBack();
            // Optionally log this: User might not have enough currency
            return false;
        }

        $this->pdo->commit();
			}
			catch ( PDOException $e )
			{
        $this->pdo->rollBack();
				HandleError($e);
        return false; // Added
			}

			return true;
		}

		/**
		 * Fetch the user's masteries.
		 */
		public function FetchMasteries($User_ID)
		{
      // Placeholder
		}

		/**
		 * Displays the user rank where applicable (staff page, profiles, etc).
		 */
		public function DisplayUserRank($UserID, $Font_Size = 18)
		{
			try
			{
				$Fetch_Rank = $this->pdo->prepare("SELECT `Rank` FROM `users` WHERE `id` = ? LIMIT 1");
				$Fetch_Rank->execute([$UserID]);
				$Fetch_Rank->setFetchMode(PDO::FETCH_ASSOC);
				$Rank = $Fetch_Rank->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return "Error"; // Added
			}

      if (!$Rank) return "Unknown Rank"; // Added

			switch($Rank['Rank'])
			{
				case 'Administrator':
					return "<div class='administrator' style='font-size: {$Font_Size}px'>Administrator</div>";
				case 'Bot':
					return "<div class='bot' style='font-size: {$Font_Size}px'>Bot</div>";
				case 'Developer':
					return "<div class='developer' style='font-size: {$Font_Size}px'>Developer</div>";
				case 'Super Moderator':
					return "<div class='super_mod' style='font-size: {$Font_Size}px'>Super Moderator</div>";
				case 'Moderator':
					return "<div class='moderator' style='font-size: {$Font_Size}px'>Moderator</div>";
				case 'Chat Moderator':
					return "<div class='chat_mod' style='font-size: {$Font_Size}px'>Chat Moderator</div>";
				case 'Member':
					return "<div class='member' style='font-size: {$Font_Size}px'>Member</div>";
        default:
          return "<div class='member' style='font-size: {$Font_Size}px'>Member</div>"; // Default case
			}
		}

		public function DisplayUserName($UserID, $Clan_Tag = false, $Display_ID = false, $Link = false)
		{
			try
			{
				$Fetch_User = $this->pdo->prepare("SELECT `id`, `Username`, `Rank` FROM `users` WHERE `id` = ? LIMIT 1");
				$Fetch_User->execute([ $UserID ]);
				$Fetch_User->setFetchMode(PDO::FETCH_ASSOC);
				$User = $Fetch_User->fetch();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
        return "Error"; // Added
			}

      if (!$User) return "Unknown User"; // Added

			if ( $Display_ID )
			{
				$Append_ID = " - #" . number_format($User['id']);
			}
			else
			{
				$Append_ID = '';
			}

			/**
			 * Hyperlink it.
			 */
			if ( $Link )
			{
				$Apply_Link_1 = "<a href='" . DOMAIN_ROOT . "/profile.php?id={$User['id']}'>";
				$Apply_Link_2 = "</a>";
			}
			else
			{
				$Apply_Link_1 = "";
				$Apply_Link_2 = "";
			}

			switch ( $User['Rank'] )
			{
				case 'Administrator':
					return "{$Apply_Link_1}<span class='administrator'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Bot':
					return "{$Apply_Link_1}<span class='bot'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Developer':
					return "{$Apply_Link_1}<span class='developer'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Super Moderator':
					return "{$Apply_Link_1}<span class='super_mod'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Moderator':
					return "{$Apply_Link_1}<span class='moderator'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Chat Moderator':
					return "{$Apply_Link_1}<span class='chat_mod'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
				case 'Member':
				default:
					return "{$Apply_Link_1}<span class='member'>{$User['Username']}{$Append_ID}</span>{$Apply_Link_2}";
			}
		}

    /**
     * Create and/or update the desired stat of a user.
     *
     * @param {int} $User_ID
     * @param {string} $Stat_Name
     * @param {int} $Stat_Value
     */
    public function UpdateStat
    (
      int $User_ID,
      string $Stat_Name,
      int $Stat_Value
    )
    {
      if ( empty($Stat_Value) || $Stat_Value == 0 ) // Allow negative values for stat reduction if ever needed
        return false;

      try
      {
        $Stat = $this->pdo->prepare("
          INSERT INTO `user_stats` (`User_ID`, `Stat_Name`, `Stat_Value`)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE `Stat_Value` = `Stat_Value` + VALUES(`Stat_Value`)
        ");
        $Stat->execute([ $User_ID, $Stat_Name, $Stat_Value ]);
      }
      catch ( PDOException $e )
      {
        HandleError($e);
        return false; // Added
      }

      return true;
    }
	}
