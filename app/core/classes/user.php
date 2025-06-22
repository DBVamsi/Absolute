<?php
/**
 * Handles user-related operations such as data fetching, authentication, user creation, and stats management.
 */
	class User
	{
		/** @var PDO */
		private $pdo;
		/** @var array<string> Whitelisted currency column names for database operations. */
		private static $VALID_CURRENCY_COLUMNS = ['Money', 'Abso_Coins'];

		/**
		 * Constructor for the User class.
		 *
		 * @param PDO $pdo The PDO database connection object.
		 */
		public function __construct(PDO $pdo)
		{
			$this->pdo = $pdo;
		}

		/**
		 * Fetches a comprehensive dataset for a specific user by their ID.
		 *
		 * @param int $User_ID The user's ID.
		 * @return array|false An associative array of user data, or false if not found or on error.
		 */
		public function FetchUserData(int $User_ID)
		{
			if ( $User_ID <= 0 )
				return false;

			// $User_Query was previously Purify($User_Query). Assuming ID, no Purify needed.
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
				$Fetch_User->execute([ $User_ID ]);
				$Fetch_User->setFetchMode(PDO::FETCH_ASSOC);
				$User = $Fetch_User->fetch();

        $Check_User_Ban = $this->pdo->prepare("
          SELECT *
          FROM `user_bans`
          WHERE `User_ID` = ?
          LIMIT 1
        ");
        $Check_User_Ban->execute([
          $User_ID // Use the integer ID
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
		 * Fetches a given user's roster Pokémon.
		 *
		 * @param int $User_ID The ID of the user whose roster to fetch.
		 * @return array|false An array of Pokémon data from the roster, or false if user not found or on error.
		 */
		public function FetchRoster(int $User_ID): array|false
		{
			if ( $User_ID <= 0 ) // More specific check for invalid ID
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
		 * Removes a specified amount of a currency from a user.
     * The currency type is validated against a whitelist `self::$VALID_CURRENCY_COLUMNS`.
		 *
		 * @param int $User_ID The ID of the user.
		 * @param string $Currency The database column name of the currency (e.g., 'Money', 'Abso_Coins').
		 * @param int $Amount The positive amount of currency to remove.
     * @return bool True on success, false on failure (e.g., insufficient funds, invalid currency type, DB error).
		 */
		public function RemoveCurrency(int $User_ID, string $Currency, int $Amount): bool
		{
			if ( $User_ID <= 0 || empty($Currency) || $Amount <= 0 )
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
		 * Fetches the user's masteries.
     * (Placeholder - To be implemented)
     *
     * @param int $User_ID The ID of the user.
     * @return array|null Returns null or an empty array as it's a placeholder.
		 */
		public function FetchMasteries(int $User_ID): ?array
		{
      // Placeholder
		}

		/**
		 * Generates an HTML string for displaying a user's rank with specific styling.
		 *
		 * @param int $UserID The ID of the user.
		 * @param int $Font_Size The font size for the rank display. Defaults to 18.
		 * @return string HTML string for the user's rank, or "Error"/"Unknown Rank" on failure/not found.
		 */
		public function DisplayUserRank(int $UserID, int $Font_Size = 18): string
		{
			if ($UserID <= 0) return "Invalid User ID"; // Basic validation

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

		/**
		 * Generates a displayable username string, optionally with rank styling, clan tag, ID, and profile link.
		 *
		 * @param int $UserID The ID of the user.
		 * @param bool $Clan_Tag Whether to include the clan tag (Not implemented in current snippet). Defaults to false.
		 * @param bool $Display_ID Whether to append the user's ID to their username. Defaults to false.
		 * @param bool $Link Whether to wrap the username in a link to their profile. Defaults to false.
		 * @return string The formatted username string (potentially HTML).
		 */
		public function DisplayUserName(int $UserID, bool $Clan_Tag = false, bool $Display_ID = false, bool $Link = false): string
		{
			if ($UserID <= 0) return "Invalid User ID"; // Basic validation
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
     * Creates and/or updates a specific statistic for a user.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE to either create the stat or add to its existing value.
     *
     * @param int $User_ID The ID of the user.
     * @param string $Stat_Name The name of the stat to update (e.g., 'Map_Pokemon_Caught').
     * @param int $Stat_Value The value to add to the stat. Can be negative for subtraction if the logic is ever intended to support that, though current check prevents 0.
     * @return bool True on success, false on failure.
     */
    public function UpdateStat(int $User_ID, string $Stat_Name, int $Stat_Value): bool
    {
      if ( $User_ID <= 0 || empty($Stat_Name) ) // Basic validation for User_ID and Stat_Name
        return false;

      // The original check `empty($Stat_Value) || $Stat_Value == 0` prevents adding 0,
      // which might be desired if a stat is being reset or explicitly set to a value that happens to be 0 after addition.
      // If the intent is strictly to increment/decrement by non-zero values, the original check is fine.
      // For a general UpdateStat that could set or increment, this check might be too restrictive.
      // Given it's an increment (`Stat_Value = Stat_Value + VALUES(Stat_Value)`), adding 0 is a no-op.
      // Preventing $Stat_Value == 0 seems reasonable if it's always an increment/decrement action.
      if ( $Stat_Value == 0 )
        return true; // Or false if 0 is considered an invalid value to operate with. Let's assume true as it's a no-op for addition.

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

    /**
     * Fetches basic site statistics (total users, total Pokémon).
     *
     * @return array Associative array with 'user_count' and 'pokemon_count'.
     */
    public function FetchSiteStatistics(): array
    {
        $stats = ['user_count' => 0, 'pokemon_count' => 0];
        try {
            $user_stmt = $this->pdo->query("SELECT COUNT(*) FROM `users`");
            if ($user_stmt) {
                $stats['user_count'] = (int)$user_stmt->fetchColumn();
            }

            $pokemon_stmt = $this->pdo->query("SELECT COUNT(*) FROM `pokemon`");
            if ($pokemon_stmt) {
                $stats['pokemon_count'] = (int)$pokemon_stmt->fetchColumn();
            }
        } catch (PDOException $e) {
            HandleError($e);
            // Stats will remain 0 on error, which is acceptable.
        }
        return $stats;
    }

    /**
     * Checks if a username already exists in the database (case-insensitive).
     *
     * @param string $username The username to check.
     * @return bool True if the username exists, false otherwise. Returns true on DB error for safety.
     */
    public function CheckUsernameExists(string $username): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT `ID` FROM `users` WHERE LOWER(`Username`) = LOWER(?) LIMIT 1");
            $stmt->execute([strtolower($username)]); // Ensure consistent case for comparison
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            HandleError($e);
            return true; // Default to true (exists) on DB error to be safe
        }
    }

    /**
     * Checks if an email address already exists in the database.
     *
     * @param string $email The email address to check.
     * @return bool True if the email exists, false otherwise. Returns true on DB error for safety.
     */
    public function CheckEmailExists(string $email): bool
    {
        // Assuming an 'Email' column exists in the 'users' table.
        try {
            $stmt = $this->pdo->prepare("SELECT `ID` FROM `users` WHERE `Email` = ? LIMIT 1");
            $stmt->execute([$email]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            HandleError($e);
            // It's safer to assume email exists on DB error to prevent duplicates if the check fails.
            return true;
        }
    }

    /**
     * Creates a new user.
     * Handles inserting records into `users`, `user_passwords`, and `user_currency` tables.
     * Does NOT handle starter Pokémon creation; that's managed by the calling controller.
     *
     * @param string $username The desired username.
     * @param string $raw_password The user's raw (unhashed) password.
     * @param string $email The user's email address.
     * @param string $gender The user's selected gender.
     * @param string $avatar_path The path to the user's selected avatar (e.g., "/Avatars/Sprites/1.png").
     * @return array An associative array: `['success' => bool, 'message' => string, 'user_id' => int|null]`
     */
    public function CreateUser(string $username, string $raw_password, string $email, string $gender, string $avatar_path): array
    {
        // Further validation could be added here (e.g. password complexity) but basic checks are in controller.

        $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);
        $signed_up_timestamp = time();
        $user_auth_code = bin2hex(random_bytes(10));

        try {
            $this->pdo->beginTransaction();

            $create_user_stmt = $this->pdo->prepare("
                INSERT INTO `users`
                  (`Username`, `Email`, `Avatar`, `Gender`, `Date_Registered`, `Auth_Code`, `Last_Active`, `Last_Page`)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            // Added Email to insert, and Last_Active, Last_Page with defaults
            $create_user_stmt->execute([
                $username,
                $email,
                $avatar_path,
                $gender,
                $signed_up_timestamp,
                $user_auth_code,
                $signed_up_timestamp, // Last_Active
                'Registered' // Last_Page
            ]);
            $new_user_id = $this->pdo->lastInsertId();

            if (!$new_user_id) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Failed to create the primary user record.'];
            }
            $new_user_id = (int)$new_user_id;

            // Create password entry
            $create_password_stmt = $this->pdo->prepare("
                INSERT INTO `user_passwords` (`ID`, `Username`, `Password`) VALUES (?, ?, ?)
            ");
            $create_password_stmt->execute([$new_user_id, $username, $password_hash]);

            // Create currency entry
            $create_currency_stmt = $this->pdo->prepare("
                INSERT INTO `user_currency` (`ID`) VALUES (?)
            "); // Assumes default 0 for currencies on new row
            $create_currency_stmt->execute([$new_user_id]);

            // Create initial stats entries (example)
            $this->UpdateStat($new_user_id, 'Map_Pokemon_Caught', 0);
            $this->UpdateStat($new_user_id, 'Map_Exp_Earned', 0);


            $this->pdo->commit();
            return ['success' => true, 'message' => 'User registered successfully.', 'user_id' => $new_user_id];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            HandleError($e);
            // Check for duplicate entry specifically for email if table structure enforces uniqueness
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'Email') !== false) {
                 return ['success' => false, 'message' => 'This email address is already registered.'];
            }
            return ['success' => false, 'message' => 'A database error occurred during user registration.'];
        }
    }

    /**
     * Authenticates a user based on username and raw password.
     *
     * @param string $username The username provided by the user.
     * @param string $raw_password The raw password provided by the user.
     * @return array An associative array: `['success' => bool, 'message' => string, 'user_id' => int|null, 'username' => string|null]`
     */
    public function AuthenticateUser(string $username, string $raw_password): array
    {
        try {
            // Fetch user by username (case-insensitive)
            $fetch_user_stmt = $this->pdo->prepare("
                SELECT `ID`, `Username`
                FROM `users`
                WHERE LOWER(`Username`) = LOWER(?)
                LIMIT 1
            ");
            $fetch_user_stmt->execute([strtolower($username)]); // Ensure consistent case for comparison
            $user_basis = $fetch_user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user_basis) {
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }

            // Fetch password hash
            $fetch_pass_stmt = $this->pdo->prepare("
                SELECT `Password`
                FROM `user_passwords`
                WHERE `ID` = ?
                LIMIT 1
            ");
            $fetch_pass_stmt->execute([$user_basis['ID']]);
            $password_hash = $fetch_pass_stmt->fetchColumn();

            if (!$password_hash) {
                // Should not happen if user exists, implies data inconsistency
                return ['success' => false, 'message' => 'Authentication error: Password record not found.'];
            }

            if (password_verify($raw_password, $password_hash)) {
                return ['success' => true, 'user_id' => (int)$user_basis['ID'], 'username' => $user_basis['Username']];
            } else {
                return ['success' => false, 'message' => 'Invalid username or password.'];
            }

        } catch (PDOException $e) {
            HandleError($e);
            return ['success' => false, 'message' => 'A database error occurred during authentication.'];
        }
    }

    /**
     * Updates the 'Last_Login' timestamp for a user to the current time.
     *
     * @param int $user_id The ID of the user to update.
     * @return bool True on success, false on failure.
     */
    public function UpdateLastLogin(int $user_id): bool
    {
        if ($user_id <= 0) return false; // Basic validation

        try {
            // Assuming a `Last_Login` column exists in the `users` table
            $stmt = $this->pdo->prepare("
                UPDATE `users`
                SET `Last_Login` = ?
                WHERE `ID` = ?
            ");
            return $stmt->execute([time(), $user_id]);
        } catch (PDOException $e) {
            HandleError($e);
            return false;
        }
    }
	}
