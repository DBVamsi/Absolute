<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/modify_user.php';

  if ( !empty($_GET['User_Value']) )
    $User_Value = Purify($_GET['User_Value']);

  if ( empty($User_Value) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'The user you are trying to modify doesn\'t exist.',
    ]);

    exit;
  }

  $User_Action_Input = $_GET['User_Action'] ?? '';
  $allowed_user_actions = ['Show', 'Update'];

  if ( !empty($User_Action_Input) && in_array($User_Action_Input, $allowed_user_actions, true) )
    $User_Action = $User_Action_Input; // Whitelisted, Purify not needed
  else
    $User_Action = null; // Or handle error explicitly

  if ( empty($User_Action) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'An invalid action was selected.',
    ]);

    exit;
  }

  $New_User_Avatar = null;
  if ( !empty($_GET['New_User_Avatar']) )
    $New_User_Avatar = Purify($_GET['New_User_Avatar']); // Avatar path/URL, Purify is fine

  $New_User_Password = null;
  if ( !empty($_GET['New_User_Password']) )
    $New_User_Password = $_GET['New_User_Password']; // Raw password, do not Purify

  try
  {
    $Check_User_Existence = $PDO->prepare("
      SELECT `ID`
      FROM `users`
      WHERE `ID` = ? OR `Username` = ?
      LIMIT 1
    ");
    $Check_User_Existence->execute([
      $User_Value,
      $User_Value
    ]);
    $Check_User_Existence->setFetchMode(PDO::FETCH_ASSOC);
    $User_Existence = $Check_User_Existence->fetch();
  }
  catch ( PDOException $e )
  {
    HandleError($e);
  }

  if ( empty($User_Existence) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'This user does not exist.',
    ]);

    exit;
  }

  switch ( $User_Action )
  {
    case 'Show':
      $Modify_User_Table = ShowModifyUserTable($User_Existence['ID']);

      echo json_encode([
        'Modify_User_Table' => $Modify_User_Table,
      ]);
      break;

    case 'Update':
      $Update_User = UpdateUser($User_Existence['ID'], $New_User_Avatar, $New_User_Password);

      echo json_encode([
        'Success' => $Update_User['Success'],
        'Message' => $Update_User['Message'],
        'Modify_User_Table' => $Update_User['New_Table_HTML'],
      ]);
      break;
  }
