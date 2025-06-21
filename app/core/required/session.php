<?php
  $page_script_start_time = microtime(true);

  // Set the timezone that Evo-Chronicles RPG is based on.
  date_default_timezone_set('America/Los_Angeles');
  $Date = date("M dS, Y g:i:s A");
  $EvoChroniclesRPG_Time = date('m/d/y h:i A');
  $Time = time();

  // Deal with the $_SERVER const.
  if ( isset($_SERVER['HTTP_HOST']) && session_status() !== PHP_SESSION_ACTIVE )
  {
    if ( $_SERVER['HTTP_HOST'] == "localhost" )
    {
      session_set_cookie_params(0, '/', 'localhost');
    }
    else
    {
      session_set_cookie_params(0, '/', 'absoluterpg.com');
    }
  }

  // No cache.
  header("Content-Type: text/html; charset=UTF-8");
  header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
  header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
  header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
  header("Pragma: no-cache");

  if ( session_status() !== PHP_SESSION_ACTIVE )
    session_start();

  if ( !isset($Dir_Root) )
    $Dir_Root = realpath($_SERVER["DOCUMENT_ROOT"]);

  /**
   * Get all necessary classes.
   */
  require_once $Dir_Root . '/core/classes/constants.php';
  $Constants = new Constants();
  require_once $Dir_Root . '/core/classes/user.php';
  // $User_Class = new User(); // Will be initialized after PDO
  require_once $Dir_Root . '/core/classes/clan.php';
  // $Clan_Class = new Clan(); // Will be initialized after PDO
  require_once $Dir_Root . '/core/classes/item.php';
  // $Item_Class = new Item(); // Will be initialized after PDO
  require_once $Dir_Root . '/core/classes/shop.php';
  // $Shop_Class = new Shop(); // Will be initialized after PDO
  require_once $Dir_Root . '/core/classes/navigation.php';
  $Navigation = new Navigation(); // Assuming Navigation doesn't need PDO for now, or will be refactored later.
  require_once $Dir_Root . '/core/classes/notification.php';
  // $Notification_Class = new Notification(); // Will be initialized after PDO
  require_once $Dir_Root . '/core/classes/pokemon_service.php';
  require_once $Dir_Root . '/staff/classes/ban_service.php';
  require_once $Dir_Root . '/staff/classes/report_service.php';
  require_once $Dir_Root . '/staff/classes/maintenance_service.php';
  require_once $Dir_Root . '/staff/classes/staff_log_service.php'; // Added StaffLogService
  require_once $Dir_Root . '/core/classes/timer.php';
  require_once $Dir_Root . '/core/classes/weighter.php';
  require_once $Dir_Root . '/core/classes/direct_message.php'; // Assuming DirectMessage doesn't need PDO or is already refactored.

  /**
   * Get all necessary functions and constants.
   */
  require_once $Dir_Root . '/core/required/domains.php';
  require_once $Dir_Root . '/core/required/database.php';
  require_once $Dir_Root . '/core/functions/formulas.php';
  require_once $Dir_Root . '/core/functions/pagination.php';
  require_once $Dir_Root . '/core/functions/purify.php';
  require_once $Dir_Root . '/core/functions/last_seen.php';
  require_once $Dir_Root . '/core/functions/is_between_dates.php';
  require_once $Dir_Root . '/core/functions/user_agent.php';

  require_once $Dir_Root . '/core/functions/pokemon.php'; // Contains remaining global functions like CalculateStat, Natures

  $pdo_instance = connect_database('evo_chronicles_rpg');
  $User_Class = new User($pdo_instance);
  $Pokemon_Service = new PokemonService($pdo_instance);
  $StaffLog_Service = new StaffLogService($pdo_instance);

  $Clan_Class = new Clan($pdo_instance);
  $Item_Class = new Item($pdo_instance, $Pokemon_Service, $User_Class);
  $Shop_Class = new Shop($pdo_instance, $Pokemon_Service, $User_Class, $Item_Class);
  $Notification_Class = new Notification($pdo_instance);
  $Ban_Service = new BanService($pdo_instance, $User_Class, $StaffLog_Service);
  $Report_Service = new ReportService($pdo_instance, $User_Class, $StaffLog_Service);
  $Maintenance_Service = new MaintenanceService($pdo_instance, $StaffLog_Service);


  /**
   * Get the client's IP address.
   */
  if ( in_array($_SERVER['REMOTE_ADDR'], []) )
  {
    $IP_List = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

    if ( $IP_List[0] != '127.0.0.1' )
    {
      $_SERVER['REMOTE_ADDR'] = $IP_List[0]; // The first proxy in the list is the client IP.
    }
  }

  /**
   * Get data about the page the client is on.
   */
  try
  {
    $Parse_URL = parse_url((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

    $Fetch_Page = $pdo_instance->prepare("SELECT * FROM `pages` WHERE `URL` = ? LIMIT 1");
    $Fetch_Page->execute([ $Parse_URL['path'] ]);
    $Fetch_Page->setFetchMode(PDO::FETCH_ASSOC);
    $Current_Page = $Fetch_Page->fetch();
  }
  catch ( PDOException $e )
  {
    HandleError($e);
  }

  if ( !$Current_Page )
  {
    $Current_Page['Name'] = 'Index';
    $Current_Page['Maintenance'] = 'no';
    $Current_Page['Logged_In'] = 'no';
  }

  /**
   * Handle active session logic at the start of page loads.
   *  - Get active user data
   *  - Update active user page info, playtime, and last page active on
   */
  if ( isset($_SESSION['EvoChroniclesRPG']) )
  {
    $User_Data = $User_Class->FetchUserData($_SESSION['EvoChroniclesRPG']['Logged_In_As']);

    if ( !isset($_SESSION['EvoChroniclesRPG']['Playtime']) )
    {
      $_SESSION['EvoChroniclesRPG']['Playtime'] = $Time;
    }

    $Playtime = $Time - $_SESSION['EvoChroniclesRPG']['Playtime'];
    $Playtime = $Playtime > 20 ? 20 : $Playtime;
    $_SESSION['EvoChroniclesRPG']['Playtime'] = $Time;

    try
    {
      $Update_Activity = $pdo_instance->prepare("INSERT INTO `logs` (`Type`, `Page`, `Data`, `User_ID`) VALUES ('pageview', ?, ?, ?)");
      $Update_Activity->execute([ $Current_Page['Name'], $Parse_URL['path'], $User_Data['ID'] ]);

      $Update_User = $pdo_instance->prepare("UPDATE `users` SET `Last_Active` = ?, `Last_Page` = ?, `Playtime` = `Playtime` + ? WHERE `ID` = ? LIMIT 1");
      $Update_User->execute([ $Time, $Current_Page['Name'], $Playtime, $User_Data['ID'] ]);
    }
    catch ( PDOException $e )
    {
      HandleError($e);
    }
  }
