<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/edit_moves.php';

  $Move_ID = null;
  if ( !empty($_GET['Move_ID']) )
    $Move_ID = (int)$_GET['Move_ID'];

  try
  {
    $Get_Move_Entry_Data = $PDO->prepare("
      SELECT `ID`
      FROM `moves`
      WHERE `ID` = ?
      LIMIT 1
    ");
    $Get_Move_Entry_Data->execute([ $Move_ID ]);
    $Get_Move_Entry_Data->setFetchMode(PDO::FETCH_ASSOC);
    $Move_Entry_Data = $Get_Move_Entry_Data->fetch();
  }
  catch ( PDOException $e )
  {
    HandleError($e);
  }

  if ( empty($Move_ID) || empty($Move_Entry_Data) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => "The move entry that you have requested doesn't exist.",
    ]);

    exit;
  }

  if ( !empty($_GET['Action']) && in_array($_GET['Action'], ['Show', 'Update']) )
    $Action = Purify($_GET['Action']);

  if ( empty($Action) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'An invalid action was selected.',
    ]);

    exit;
  }

  $Name = null;
  if ( !empty($_GET['Name']) )
    $Name = Purify($_GET['Name']);

  $Class_Name = null;
  if ( !empty($_GET['Class_Name']) )
    $Class_Name = Purify($_GET['Class_Name']);

  $Accuracy = null;
  if ( !empty($_GET['Accuracy']) )
    $Accuracy = (int)$_GET['Accuracy'];

  $Power = null;
  if ( !empty($_GET['Power']) )
    $Power = (int)$_GET['Power'];

  $Priority = null;
  if ( !empty($_GET['Priority']) )
    $Priority = (int)$_GET['Priority'];

  $PP = null;
  if ( !empty($_GET['PP']) )
    $PP = (int)$_GET['PP'];

  $Damage_Type = null;
  if ( !empty($_GET['Damage_Type']) )
    $Damage_Type = Purify($_GET['Damage_Type']); // String, Purify fine

  $Move_Type = null;
  if ( !empty($_GET['Move_Type']) )
    $Move_Type = Purify($_GET['Move_Type']); // String, Purify fine

  $Category = null;
  if ( !empty($_GET['Category']) )
    $Category = Purify($_GET['Category']); // String, Purify fine

  $Ailment = null;
  if ( !empty($_GET['Ailment']) )
    $Ailment = Purify($_GET['Ailment']); // String, Purify fine

  $Flinch_Chance = null;
  if ( !empty($_GET['Flinch_Chance']) )
    $Flinch_Chance = (int)$_GET['Flinch_Chance'];

  $Crit_Chance = null;
  if ( !empty($_GET['Crit_Chance']) )
    $Crit_Chance = (int)$_GET['Crit_Chance'];

  $Effect_Chance = null;
  if ( !empty($_GET['Effect_Chance']) )
    $Effect_Chance = (int)$_GET['Effect_Chance'];

  $Ailment_Chance = null;
  if ( !empty($_GET['Ailment_Chance']) )
    $Ailment_Chance = (int)$_GET['Ailment_Chance'];

  $HP_Boost = null;
  if ( !empty($_GET['HP_Boost']) )
    $HP_Boost = (int)$_GET['HP_Boost'];

  $Attack_Boost = null;
  if ( !empty($_GET['Attack_Boost']) )
    $Attack_Boost = (int)$_GET['Attack_Boost'];

  $Defense_Boost = null;
  if ( !empty($_GET['Defense_Boost']) )
    $Defense_Boost = (int)$_GET['Defense_Boost'];

  $Sp_Attack_Boost = null;
  if ( !empty($_GET['Sp_Attack_Boost']) )
    $Sp_Attack_Boost = (int)$_GET['Sp_Attack_Boost'];

  $Sp_Defense_Boost = null;
  if ( !empty($_GET['Sp_Defense_Boost']) )
    $Sp_Defense_Boost = (int)$_GET['Sp_Defense_Boost'];

  $Speed_Boost = null;
  if ( !empty($_GET['Speed_Boost']) )
    $Speed_Boost = (int)$_GET['Speed_Boost'];

  $Accuracy_Boost = null;
  if ( !empty($_GET['Accuracy_Boost']) )
    $Accuracy_Boost = (int)$_GET['Accuracy_Boost'];

  $Evasion_Boost = null;
  if ( !empty($_GET['Evasion_Boost']) )
    $Evasion_Boost = (int)$_GET['Evasion_Boost'];

  $Min_Hits = null;
  if ( !empty($_GET['Min_Hits']) )
    $Min_Hits = (int)$_GET['Min_Hits'];

  $Max_Hits = null;
  if ( !empty($_GET['Max_Hits']) )
    $Max_Hits = (int)$_GET['Max_Hits'];

  $Min_Turns = null;
  if ( !empty($_GET['Min_Turns']) )
    $Min_Turns = (int)$_GET['Min_Turns'];

  $Max_Turns = null;
  if ( !empty($_GET['Max_Turns']) )
    $Max_Turns = (int)$_GET['Max_Turns'];

  $Recoil = null;
  if ( !empty($_GET['Recoil']) )
    $Recoil = (int)$_GET['Recoil'];

  $Drain = null;
  if ( !empty($_GET['Drain']) )
    $Drain = (int)$_GET['Drain'];

  $Healing = null;
  if ( !empty($_GET['Healing']) )
    $Healing = (int)$_GET['Healing'];

  $Stat_Chance = null;
  if ( !empty($_GET['Stat_Chance']) )
    $Stat_Chance = (int)$_GET['Stat_Chance'];

  $authentic = null;
  if ( !empty($_GET['authentic']) )
    $authentic = (int)$_GET['authentic'];

  $bite = null;
  if ( !empty($_GET['bite']) )
    $bite = (int)$_GET['bite'];

  $bullet = null;
  if ( !empty($_GET['bullet']) )
    $bullet = (int)$_GET['bullet'];

  $charge = null;
  if ( !empty($_GET['charge']) )
    $charge = (int)$_GET['charge'];

  $contact = null;
  if ( !empty($_GET['contact']) )
    $contact = (int)$_GET['contact'];

  $dance = null;
  if ( !empty($_GET['dance']) )
    $dance = (int)$_GET['dance'];

  $defrost = null;
  if ( !empty($_GET['defrost']) )
    $defrost = (int)$_GET['defrost'];

  $distance = null;
  if ( !empty($_GET['distance']) )
    $distance = (int)$_GET['distance'];

  $gravity = null;
  if ( !empty($_GET['gravity']) )
    $gravity = (int)$_GET['gravity'];

  $heal = null;
  if ( !empty($_GET['heal']) )
    $heal = (int)$_GET['heal'];

  $mirror = null;
  if ( !empty($_GET['mirror']) )
    $mirror = (int)$_GET['mirror'];

  $mystery = null;
  if ( !empty($_GET['mystery']) )
    $mystery = (int)$_GET['mystery'];

  $nonsky = null;
  if ( !empty($_GET['nonsky']) )
    $nonsky = (int)$_GET['nonsky'];

  $powder = null;
  if ( !empty($_GET['powder']) )
    $powder = (int)$_GET['powder'];

  $protect = null;
  if ( !empty($_GET['protect']) )
    $protect = (int)$_GET['protect'];

  $pulse = null;
  if ( !empty($_GET['pulse']) )
    $pulse = (int)$_GET['pulse'];

  $punch = null;
  if ( !empty($_GET['punch']) )
    $punch = (int)$_GET['punch'];

  $recharge = null;
  if ( !empty($_GET['recharge']) )
    $recharge = (int)$_GET['recharge'];

  $reflectable = null;
  if ( !empty($_GET['reflectable']) )
    $reflectable = (int)$_GET['reflectable'];

  $snatch = null;
  if ( !empty($_GET['snatch']) )
    $snatch = (int)$_GET['snatch'];

  $sound = null;
  if ( !empty($_GET['sound']) )
    $sound = (int)$_GET['sound'];


  switch ( $Action )
  {
    case 'Show':
      $Move_Edit_Table = ShowMoveEditTable($Move_ID);

      echo json_encode([
        'Move_Edit_Table' => $Move_Edit_Table,
      ]);
      break;

    case 'Update':
      $Update_Move_Entry = UpdateMoveData(
        $Move_ID, $Name, $Class_Name, $Accuracy, $Power, $Priority, $PP, $Damage_Type, $Move_Type, $Category, $Ailment, $Flinch_Chance, $Crit_Chance, $Effect_Chance, $Ailment_Chance, $HP_Boost, $Attack_Boost, $Defense_Boost, $Sp_Attack_Boost, $Sp_Defense_Boost, $Speed_Boost, $Accuracy_Boost, $Evasion_Boost, $Min_Hits, $Max_Hits, $Min_Turns, $Max_Turns, $Recoil, $Drain, $Healing, $Stat_Chance, $authentic, $bite, $bullet, $charge, $contact, $dance, $defrost, $distance, $gravity, $heal, $mirror, $mystery, $nonsky, $powder, $protect, $pulse, $punch, $recharge, $reflectable, $snatch, $sound
      );

      echo json_encode([
        'Success' => $Update_Move_Entry['Success'],
        'Message' => $Update_Move_Entry['Message'],
        'Move_Edit_Table' => ShowMoveEditTable($Move_ID),
      ]);
      break;
  }
