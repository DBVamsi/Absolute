<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/staff/functions/edit_pokedex.php';

  $Pokedex_ID = null;
  if ( !empty($_GET['Pokedex_ID']) )
    $Pokedex_ID = (int)$_GET['Pokedex_ID'];

  try
  {
    $Get_Pokedex_Entry_Data = $PDO->prepare("
      SELECT `ID`
      FROM `pokedex`
      WHERE `ID` = ?
      LIMIT 1
    ");
    $Get_Pokedex_Entry_Data->execute([ $Pokedex_ID ]);
    $Get_Pokedex_Entry_Data->setFetchMode(PDO::FETCH_ASSOC);
    $Pokedex_Entry_Data = $Get_Pokedex_Entry_Data->fetch();
  }
  catch ( PDOException $e )
  {
    HandleError($e);
  }

  if ( empty($Pokedex_ID) || empty($Pokedex_Entry_Data) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => "The Pok&eacute;dex entry that you have requested doesn't exist.",
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

  $Pokemon = null;
  if ( !empty($_GET['Pokemon']) )
    $Pokemon = Purify($_GET['Pokemon']);

  $Forme = null;
  if ( !empty($_GET['Forme']) )
    $Forme = Purify($_GET['Forme']);

  $Type_Primary = null;
  if ( !empty($_GET['Type_Primary']) )
    $Type_Primary = Purify($_GET['Type_Primary']);

  $Type_Secondary = null;
  if ( !empty($_GET['Type_Secondary']) )
    $Type_Secondary = Purify($_GET['Type_Secondary']);

  $Base_HP = null;
  if ( !empty($_GET['Base_HP']) )
    $Base_HP = (int)$_GET['Base_HP'];

  $Base_Attack = null;
  if ( !empty($_GET['Base_Attack']) )
    $Base_Attack = (int)$_GET['Base_Attack'];

  $Base_Defense = null;
  if ( !empty($_GET['Base_Defense']) )
    $Base_Defense = (int)$_GET['Base_Defense'];

  $Base_Sp_Attack = null;
  if ( !empty($_GET['Base_Sp_Attack']) )
    $Base_Sp_Attack = (int)$_GET['Base_Sp_Attack'];

  $Base_Sp_Defense = null;
  if ( !empty($_GET['Base_Sp_Defense']) )
    $Base_Sp_Defense = (int)$_GET['Base_Sp_Defense'];

  $Base_Speed = null;
  if ( !empty($_GET['Base_Speed']) )
    $Base_Speed = (int)$_GET['Base_Speed'];

  $HP_EV = null;
  if ( !empty($_GET['HP_EV']) )
    $HP_EV = (int)$_GET['HP_EV'];

  $Attack_EV = null;
  if ( !empty($_GET['Attack_EV']) )
    $Attack_EV = (int)$_GET['Attack_EV'];

  $Defense_EV = null;
  if ( !empty($_GET['Defense_EV']) )
    $Defense_EV = (int)$_GET['Defense_EV'];

  $Sp_Attack_EV = null;
  if ( !empty($_GET['Sp_Attack_EV']) )
    $Sp_Attack_EV = (int)$_GET['Sp_Attack_EV'];

  $Sp_Defense_EV = null;
  if ( !empty($_GET['Sp_Defense_EV']) )
    $Sp_Defense_EV = (int)$_GET['Sp_Defense_EV'];

  $Speed_EV = null;
  if ( !empty($_GET['Speed_EV']) )
    $Speed_EV = (int)$_GET['Speed_EV'];

  $Female_Odds = null;
  if ( !empty($_GET['Female_Odds']) )
    $Female_Odds = (float)$_GET['Female_Odds'];

  $Male_Odds = null;
  if ( !empty($_GET['Male_Odds']) )
    $Male_Odds = (float)$_GET['Male_Odds'];

  $Genderless_Odds = null;
  if ( !empty($_GET['Genderless_Odds']) )
    $Genderless_Odds = (float)$_GET['Genderless_Odds'];

  $Height = null;
  if ( !empty($_GET['Height']) )
    $Height = (float)$_GET['Height'];

  $Weight = null;
  if ( !empty($_GET['Weight']) )
    $Weight = (float)$_GET['Weight'];

  $Exp_Yield = null;
  if ( !empty($_GET['Exp_Yield']) )
    $Exp_Yield = (int)$_GET['Exp_Yield'];

  $Is_Baby = null;
  if ( !empty($_GET['Is_Baby']) )
    $Is_Baby = (int)$_GET['Is_Baby'];

  $Is_Mythical = null;
  if ( !empty($_GET['Is_Mythical']) )
    $Is_Mythical = (int)$_GET['Is_Mythical'];

  $Is_Legendary = null;
  if ( !empty($_GET['Is_Legendary']) )
    $Is_Legendary = (int)$_GET['Is_Legendary'];

  switch ( $Action )
  {
    case 'Show':
      $Edit_Table = ShowEntryEditTable($Pokedex_ID);

      echo json_encode([
        'Edit_Table' => $Edit_Table,
      ]);
      break;

    case 'Update':
      $Update_Pokedex_Entry = UpdatePokedexEntry(
        $Pokedex_ID,
        $Pokemon,
        $Forme,
        $Type_Primary,
        $Type_Secondary,
        $Base_HP,
        $Base_Attack,
        $Base_Defense,
        $Base_Sp_Attack,
        $Base_Sp_Defense,
        $Base_Speed,
        $HP_EV,
        $Attack_EV,
        $Defense_EV,
        $Sp_Attack_EV,
        $Sp_Defense_EV,
        $Speed_EV,
        $Female_Odds,
        $Male_Odds,
        $Genderless_Odds,
        $Height,
        $Weight,
        $Exp_Yield,
        $Is_Baby,
        $Is_Mythical,
        $Is_Legendary
      );

      echo json_encode([
        'Success' => $Update_Pokedex_Entry['Success'],
        'Message' => $Update_Pokedex_Entry['Message'],
        'Pokedex_Edit_Table' => ShowEntryEditTable($Pokedex_ID),
      ]);
  }
