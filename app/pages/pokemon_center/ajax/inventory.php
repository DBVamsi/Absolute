<?php
  require_once $_SERVER['DOCUMENT_ROOT'] . '/core/required/session.php';
  require_once $_SERVER['DOCUMENT_ROOT'] . '/pages/pokemon_center/functions/inventory.php';

  $Action_Input = $_GET['Action'] ?? '';
  $allowed_actions = ['Equip_Item', 'Unequip_Item', 'Show_Inventory', 'Show_Equipped_Items', 'Show_Item_Preview'];
  $Action = null;

  if ( !empty($Action_Input) && in_array($Action_Input, $allowed_actions, true) )
    $Action = $Action_Input; // Whitelisted, Purify not needed

  if ( empty($Action) )
  {
    echo json_encode([
      'Success' => false,
      'Message' => 'An invalid action was selected.',
    ]);

    exit;
  }

  $Pokemon_ID = null;
  if ( !empty($_GET['Pokemon_ID']) )
    $Pokemon_ID = (int)$_GET['Pokemon_ID'];

  $Item_ID = null;
  if ( !empty($_GET['Item_ID']) )
    $Item_ID = (int)$_GET['Item_ID'];

  $Inventory_Tab = 'Held Item'; // Default
  $allowed_inventory_tabs = ['Held Item', 'General Item', 'Battle Item', 'Medicine', 'Berries', 'Pokeballs', 'Key Item', 'All']; // Added Pokeballs, Key Item, All from inventory.js ShowInventoryTab
  $Inventory_Tab_Input = $_GET['Inventory_Tab'] ?? '';

  if ( !empty($Inventory_Tab_Input) && in_array($Inventory_Tab_Input, $allowed_inventory_tabs, true) )
    $Inventory_Tab = $Inventory_Tab_Input; // Whitelisted, Purify not needed

  switch ( $Action )
  {
    case 'Show_Equipped_Items':
      echo json_encode([
        'Equipped_Items' => GetEquippedItems()
      ]);
      break;

    case 'Show_Inventory':
      echo json_encode([
        'Items' => GetInventoryItems($Inventory_Tab)
      ]);
      break;

    case 'Show_Item_Preview':
      echo json_encode([
        'Item_Data' => GetItemPreview($Item_ID)
      ]);
      break;

    case 'Unequip_Item':
      $Unequip_Item = UnequipItem($Pokemon_ID);

      echo json_encode([
        'Success' => $Unequip_Item['Success'],
        'Message' => $Unequip_Item['Message'],
      ]);
      break;

    case 'Equip_Item':
      $Equip_Item = EquipItem($Item_ID, $Pokemon_ID);

      echo json_encode([
        'Success' => $Equip_Item['Success'],
        'Message' => $Equip_Item['Message'],
      ]);
      break;
  }
