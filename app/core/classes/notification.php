<?php
	class Notification
	{
		private $pdo;
		public $Purify; // This property was in the original code, but not initialized or used. Retaining it for now.
		public $User_Data;

		public function __construct(PDO $pdo)
		{
			$this->pdo = $pdo;

			global $User_Data; // User_Data is still global here as per task scope.
			$this->User_Data = $User_Data;
		}

		/**
		 * Send a notification to a user.
		 */
		public function SendNotification($Sent_By, $Sent_To, $Message)
		{
			$Sent_By = Purify($Sent_By);
			$Sent_To = Purify($Sent_To);
			$Message = Purify($Message);

			try
			{
				$Insert = $this->pdo->prepare("INSERT INTO `notifications` (`Message`, `Sent_To`, `Sent_By`, `Sent_On`) VALUES (?, ?, ?, ?)");
				$Insert->execute([ $Message, $Sent_To, $Sent_By, time() ]);
			}
			catch ( PDOException $e )
			{
				HandleError($e);
			}
		}

		/**
		 * Display any unseen notifications to the appropriate user.
		 */
		public function ShowNotification($User_ID)
		{
			$User = Purify($User_ID);

			/**
			 * Fetch all unseen notifications so that they may be displayed.
			 * Also set all unseen notifications to seen, so they won't be displayed again.
			 */
			try
			{
				$Fetch_Notification = $this->pdo->prepare("SELECT * FROM `notifications` WHERE `Sent_To` = ? AND `Seen` = 'no'");
				$Fetch_Notification->execute([ $User ]);
				$Fetch_Notification->setFetchMode(PDO::FETCH_ASSOC);
				$Notifications = $Fetch_Notification->fetchAll();

				/**
				 * Loop through each unseen notification.
				 */
				if ( $Notifications && count($Notifications) > 0 )
				{
					foreach ( $Notifications as $Key => $Value )
					{
						/**
						 * Set the seen status of the notification to 'yes', so it doesn't get displayed anymore.
						 */
						$Update_Notification = $this->pdo->prepare("UPDATE `notifications` SET `Seen` = 'yes' WHERE `ID` = ?");
						$Update_Notification->execute([ $Value['ID'] ]);

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
				}
			}
			catch ( PDOException $e )
			{
				HandleError($e);
			}
		}
	}
