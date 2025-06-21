<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  // require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/ban.php'; // Removed, functionality is in BanService

  if ( !isset($User_Data) ) // Ensure User_Data is set (user is logged in and is staff)
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'You must be logged in as staff to perform this action.',
    ]);
    exit;
  }

  if ( !empty($_GET['User_Value']) && in_array(gettype($_GET['User_Value']), ['integer', 'string']) )
    $User_Value = Purify($_GET['User_Value']);

  if ( empty($User_Value) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'The user you are trying to ban doesn\'t exist.',
    ]);

    exit;
  }

  $Ban_Type = 'RPG';
  if ( !empty($_GET['Ban_Type']) && in_array($_GET['Ban_Type'], ['RPG', 'Chat']) )
    $Ban_Type = Purify($_GET['Ban_Type']);

  $Unban_Date = null;
  if ( !empty($_GET['Unban_Date']) && gettype($_GET['Unban_Date']) === 'string' && strlen($_GET['Unban_Date']) == 8 )
    $Unban_Date = Purify($_GET['Unban_Date']);
  else if ( !empty($_GET['Unban_Date']) ) // if it's set but not 8 chars, treat as invalid / permanent
    $Unban_Date = null;


  $Ban_Reason = 'No ban reason was set.';
  if ( !empty($_GET['Ban_Reason']) && gettype($_GET['Ban_Reason']) === 'string' )
    $Ban_Reason = Purify($_GET['Ban_Reason']);

  $Staff_Notes = 'No staff notes were set.';
  if ( !empty($_GET['Staff_Notes']) && gettype($_GET['Staff_Notes']) === 'string' )
    $Staff_Notes = Purify($_GET['Staff_Notes']);

  // Use BanService to fetch user
  $User_Existence = $Ban_Service->FetchUserForBan($User_Value);

  if ( !$User_Existence )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'The user you are trying to ban doesn\'t exist.',
    ]);

    exit;
  }

  // Use BanService to fetch ban status
  $User_Ban = $Ban_Service->FetchUserBanStatus($User_Existence['ID']);

  if ( !empty($User_Ban) )
  {
    // Check RPG Ban status only if attempting an RPG Ban
    if ( $Ban_Type == 'RPG' && $User_Ban['RPG_Ban'] )
    {
      echo json_encode([
        'Success' => false,
        'Message' => "{$User_Existence['Username']} is already RPG banned.",
      ]);

      exit;
    }

    // Check Chat Ban status only if attempting a Chat Ban
    if ( $Ban_Type == 'Chat' && $User_Ban['Chat_Ban'] )
    {
      echo json_encode([
        'Success' => false,
        'Message' => "{$User_Existence['Username']} is already chat banned.",
      ]);

      exit;
    }
  }

  $Success = false;
  switch ( $Ban_Type )
  {
    case 'RPG':
      $Success = $Ban_Service->RPGBanUser($User_Existence['ID'], 1, $Ban_Reason, $Staff_Notes, $Unban_Date, $User_Data['ID']);
      break;

    case 'Chat':
      $Success = $Ban_Service->ChatBanUser($User_Existence['ID'], 1, $Ban_Reason, $Staff_Notes, $Unban_Date, $User_Data['ID']);
      break;
  }

  if ($Success)
  {
    echo json_encode([
      'Success' => true,
      'Message' => "{$User_Existence['Username']} has been {$Ban_Type} banned.",
    ]);
  }
  else
  {
    echo json_encode([
      'Success' => false,
      'Message' => "An error occurred while trying to ban {$User_Existence['Username']}.",
    ]);
  }
