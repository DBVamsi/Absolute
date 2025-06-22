<?php
  // Note: These require_once statements load procedural function files.
  // CheckUserPermission and GetActiveReports are used.
  // Ideally, their logic would be part of services injected into this class if needed,
  // or this class would receive pre-calculated permissions/counts.
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/permissions.php'; // For CheckUserPermission
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/report.php';     // For GetActiveReports

/**
 * Handles rendering of the site's main navigation bar.
 * Generates HTML based on user's permissions and current page context.
 */
	Class Navigation
	{
    /** @var PDO */
    private $pdo;

		/**
		 * Constructor for the Navigation class.
		 *
     * @param PDO $pdo The PDO database connection object.
		 */
		public function __construct(PDO $pdo)
		{
      $this->pdo = $pdo;
		}

    /**
     * Renders the navigation bar HTML.
     *
     * @param string $Class The class of navigation to render (e.g., 'Staff', 'Member').
     * @param array|null $User_Data The currently logged-in user's data. Required for personalized links and permissions.
     * @return void This method echoes HTML directly.
     */
    public function Render(string $Class, ?array $User_Data): void
		{
      // global $PDO; // Replaced by $this->pdo
			// global $User_Data; // Now passed as parameter

			if (!$User_Data) {
        // Handle cases where User_Data might be essential but not provided
        // For now, assume it's provided if personalized links are expected.
        // Or, render a very basic nav if no User_Data.
        // Current logic heavily relies on $User_Data['Is_Staff'] etc.
        // For a public nav, $User_Data might be null.
        // This function as-is expects $User_Data for most paths.
        // For simplicity, if no User_Data, assume a guest-like state or minimal nav.
        // The original code implicitly expected $User_Data to be a global.
        // Let's make it explicit: if no User_Data, it can't do much of its current logic.
        // However, the original code would have errored if $User_Data global wasn't set.
        // For a robust solution, this method would need to handle $User_Data === null gracefully
        // by perhaps only showing public links.
        // For this refactor, we'll assume $User_Data is provided if user-specific nav is needed.
			}

			/**
			 * Parse the current URL.
			 */
			$URL = parse_url((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

			/**
			 * Call for the necessary pages via the `pages` table.
			 */
			try
			{
				$Query_Headers = $this->pdo->prepare("SELECT * FROM `navigation` WHERE `Class` = ? AND `Type` = 'Header'");
				$Query_Headers->execute([ $Class ]);
				$Headers = $Query_Headers->fetchAll(); // Default fetch mode is PDO::FETCH_ASSOC from session.php

				$Query_Links = $this->pdo->prepare("SELECT * FROM `navigation` WHERE `Class` = ? AND `Type` = 'Link'");
				$Query_Links->execute([ $Class ]);
				$Links = $Query_Links->fetchAll();
			}
			catch ( PDOException $e )
			{
				HandleError($e);
			}

			echo "
				<nav>
          <button id='navButton'>
            <svg viewBox='0 0 22 22' preserveAspectRatio='xMidYMid meet'>
              <g>
                <path d='M21,6H3V5h18V6z M21,11H3v1h18V11z M21,17H3v1h18V17z'></path>
              </g>
            </svg>
          </button>

          <button id='chatButton'>
            <img src='" . htmlspecialchars(DOMAIN_SPRITES . "/Pokemon/Icons/Normal/359.png", ENT_QUOTES | ENT_HTML5, 'UTF-8') . "' alt='Chat' />
          </button>
			";

			// Display the Staff Panel button/Index button, given the user is a staff member.
      // Ensure $User_Data is available before accessing its keys.
			if ( isset($User_Data['Is_Staff']) && $User_Data['Is_Staff'] )
			{
				if ( strpos($URL['path'], '/staff/') === false )
				{
					$Link_URL_Base = DOMAIN_ROOT . '/staff/';
					$Link_Name_Base = 'Staff Panel';
				}
				else
				{
					$Link_URL_Base = DOMAIN_ROOT . '/news.php';
					$Link_Name_Base = 'Index';
				}

        $Notification_Count = 0; // Use a different name to avoid confusion if $Notification_Amount is used later
        $Notification_HTML = '';

        // GetActiveReports() is a global function from procedural file.
        // This should ideally be part of a service.
        $Reported_Users_Count = count(GetActiveReports());
        if ( $Reported_Users_Count > 0 )
          $Notification_Count += $Reported_Users_Count;

        if ( $Notification_Count > 0 && $Link_Name_Base == 'Staff Panel' )
          $Notification_HTML = " (<b style='color: red;'>" . htmlspecialchars((string)$Notification_Count, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</b>)";

        $Link_URL_Escaped = htmlspecialchars($Link_URL_Base, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $Link_Name_Escaped = htmlspecialchars($Link_Name_Base, ENT_QUOTES | ENT_HTML5, 'UTF-8');

				echo "
					<div class='nav-container'>
            <div class='button'>
              <a href='{$Link_URL_Escaped}'>{$Link_Name_Escaped}{$Notification_HTML}</a>
            </div>
				";
			}
			else
			{
				echo "
					<div class='nav-container'>
				";
			}

			// Loop through navigation headers.
			$Display_Links_HTML = ''; // Changed variable name for clarity
			foreach ( $Headers as $Key => $Head )
			{
        // CheckUserPermission is a global function from procedural file.
        if ( $Class == 'Staff' && (!isset($Head['Required_Permission']) || !CheckUserPermission($Head['Required_Permission'])) ) {
          continue;
        }

				foreach ( $Links as $Link_Key => $Link ) // Changed $Key to $Link_Key to avoid conflict
				{
					if ( ($Link['Hidden'] ?? 'no') == 'yes' ) // Handle case where 'Hidden' might not be set
					{
						continue;
					}

					if ( $Link['Menu'] === $Head['Menu'] && CheckUserPermission($Link['Required_Permission']) )
					{
            $Notification_HTML_Link = ''; // For individual link notifications
            $Link_Name_Escaped = htmlspecialchars($Link['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $Link_URLEscaped = htmlspecialchars($Link['Link'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

						if ( $Class == 'Staff' )
						{
              // Example for individual link notifications if needed in future
              if ( $Link['Name'] == 'Reported Users' ) {
                $Reported_Users_Count_Link = count(GetActiveReports());
                if ( $Reported_Users_Count_Link > 0 )
                  $Notification_HTML_Link = " (<b style='color: red;'>" . htmlspecialchars((string)$Reported_Users_Count_Link, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</b>)";
              }
              // Note: Using LoadPage with user-influenced Link['Link'] can be risky if Link['Link'] isn't strictly controlled.
              // Assuming Link['Link'] is safe admin-defined slugs.
							$Display_Links_HTML .= "
								<div class='dropdown-item'>
									<a href='javascript:void(0);' onclick='LoadPage(\"/staff/{$Link_URLEscaped}\");'>{$Link_Name_Escaped}{$Notification_HTML_Link}</a>
								</div>
							";
						}
						else // Regular member links
						{
							$Display_Links_HTML .= "
								<div class='dropdown-item'>
									<a href='" . htmlspecialchars(DOMAIN_ROOT, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "{$Link_URLEscaped}'>{$Link_Name_Escaped}</a>
								</div>
							";
						}
					}
				}

        $Head_Name_Escaped = htmlspecialchars($Head['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				echo "
					<div class='nav-item has-dropdown'>
            <a href='javascript:void(0);'>
							<span>{$Head_Name_Escaped}</span>
						</a>
						<ul class='dropdown'>
							{$Display_Links_HTML}
						</ul>
					</div>
				";

				$Display_Links_HTML = ''; // Reset for next header
			}

			echo "
					</div>
				</nav>
			";
		}
	}
