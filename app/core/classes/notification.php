<?php
/**
 * Handles sending and displaying user notifications.
 * Note: This class currently uses global $User_Data in constructor, which is not ideal and should be refactored.
 */
	class Notification
	{
		/** @var PDO The PDO database connection object. */
		private $pdo;
		/**
     * @var mixed Unused Purify property from original code.
     * TODO: Remove if truly unused or implement if intended.
     */
		public $Purify;
    /**
     * @var array|null Stores global $User_Data if available.
     * TODO: Refactor to avoid global state. User context should be passed explicitly to methods needing it.
     */
		public $User_Data;

    /**
     * Constructor for Notification service.
     *
     * @param PDO $pdo The PDO database connection object.
     */
		public function __construct(PDO $pdo)
		{
			$this->pdo = $pdo;

			// TODO: Refactor to remove global $User_Data dependency.
			global $User_Data;
			$this->User_Data = $User_Data;
		}

		/**
		 * Sends a notification from one user/entity to another user.
		 * The message content is purified before being stored in the database.
		 *
     * @param int $Sent_By The User ID of the sender (0 can be used for system notifications).
     * @param int $Sent_To The User ID of the recipient.
     * @param string $Message The content of the notification.
     * @return void This method does not return a value.
		 */
		public function SendNotification(int $Sent_By, int $Sent_To, string $Message): void
		{
      // IDs should be integers. Message content is purified.
      // Allow Sent_By to be 0 for system messages.
      $Sent_By_Validated = ($Sent_By < 0) ? 0 : (int)$Sent_By;
      $Sent_To_Validated = (int)$Sent_To;
      $Message_Sanitized = Purify($Message); // Assuming Purify makes it safe for DB and later HTML display

      if ($Sent_To_Validated <= 0 || empty($Message_Sanitized)) { // Sent_By can be 0 (system)
        error_log("SendNotification: Invalid parameters. Sent_To: {$Sent_To}, Message empty: " . (empty($Message_Sanitized) ? 'Yes' : 'No'));
        return;
      }

			try
			{
				$Insert = $this->pdo->prepare("INSERT INTO `notifications` (`Message`, `Sent_To`, `Sent_By`, `Sent_On`) VALUES (:message, :sent_to, :sent_by, :sent_on)");
				$Insert->execute([
          ':message' => $Message_Sanitized,
          ':sent_to' => $Sent_To_Validated,
          ':sent_by' => $Sent_By_Validated,
          ':sent_on' => time()
        ]);
			}
			catch ( PDOException $e )
			{
				HandleError($e); // Log error
			}
		}

		/**
		 * Fetches unseen notifications for a user, displays them by echoing HTML, and then marks them as seen.
		 * Note: Directly echoing HTML is not ideal for a service class. This method should ideally
		 * return notification data, and a separate view component should handle rendering.
     * Note: Marking as seen happens for all fetched notifications, regardless of display success.
		 *
     * @param int $User_ID The ID of the user whose notifications are to be shown.
     * @return void This method directly echoes HTML and does not return a value.
		 */
		public function ShowNotification(int $User_ID): void
		{
      if ($User_ID <= 0) return; // Basic validation

			/**
			 * Fetch all unseen notifications so that they may be displayed.
			 */
			try
			{
				$Fetch_Notification = $this->pdo->prepare("SELECT `ID`, `Message` FROM `notifications` WHERE `Sent_To` = ? AND `Seen` = 'no'");
				$Fetch_Notification->execute([ $User_ID ]);
				$Fetch_Notification->setFetchMode(PDO::FETCH_ASSOC);
				$Notifications = $Fetch_Notification->fetchAll();

				if ( $Notifications && count($Notifications) > 0 )
				{
          // Begin transaction for updating multiple notifications
          $this->pdo->beginTransaction();
					foreach ( $Notifications as $Key => $Value )
					{
						// Set the seen status of the notification to 'yes'
						$Update_Notification = $this->pdo->prepare("UPDATE `notifications` SET `Seen` = 'yes' WHERE `ID` = ?");
						$Update_Notification->execute([ $Value['ID'] ]);

            // $Value['Message'] was Purify'd on input by SendNotification.
            // Assuming Purify makes it safe for direct HTML output if it allows safe HTML tags.
            // If only plain text is expected, htmlspecialchars($Value['Message']) would be safer here.
						echo "
							<div class='notification'>
								<div style='float: right;'>
									<a href='javascript:void(0);' onclick='$(this).parent().parent().hide();'>
										<b>x</b>
									</a>
								</div>

								{$Value['Message']}
							</div>
						";
					}
          $this->pdo->commit();
				}
			}
			catch ( PDOException $e )
			{
				HandleError($e);
			}
		}
	}
