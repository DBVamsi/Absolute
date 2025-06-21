<?php
  /**
   * Get the user's items within the specified inventory tab.
   *
   * @param $Inventory_Tab
   */
  function GetInventoryItems
  (
    $Inventory_Tab
  )
  {
    global $Item_Service, $User_Data; // Use ItemService

    // Direct PDO query is now encapsulated in ItemService
    return $Item_Service->GetUserInventoryByCategory($User_Data['ID'], $Inventory_Tab);
  }

  /**
   * Get the user's items that are currently equipped to Pokemon.
   */
  function GetEquippedItems()
  {
    global $Pokemon_Service, $Item_Class, $User_Data; // Item_Class for FetchItemData

    // Direct PDO query is now encapsulated in PokemonService
    $Equipped_Pokemon_With_Items = $Pokemon_Service->GetPokemonWithItems($User_Data['ID']);

    $Formatted_Equipped_Items = [];
    if ( !empty($Equipped_Pokemon_With_Items) )
    {
      foreach ( $Equipped_Pokemon_With_Items as $Pokemon_With_Item )
      {
        // GetPokemonData is already part of PokemonService, but we have basic info.
        // FetchItemData is part of Item_Class (which is ItemService instance)
        $Item_Info = $Item_Class->FetchItemData($Pokemon_With_Item['Item_ID']); // Item_ID from the new service method

        if ($Item_Info)
        {
          $Formatted_Equipped_Items[] = [
            'Pokemon' => [
              'ID' => $Pokemon_With_Item['ID'], // Pokemon ID
              'Name' => $Pokemon_With_Item['Display_Name'], // Pokemon Name from new service method
              'Icon' => $Pokemon_With_Item['Icon'], // Pokemon Icon from new service method
            ],
            'Item' => [
              'ID' => $Item_Info['ID'],
              'Name' => $Item_Info['Name'],
              'Icon' => $Item_Info['Icon']
            ]
          ];
        }
      }
    }

    return $Formatted_Equipped_Items;
  }

  /**
   * Show a preview of the specified item.
   *
   * @param $Item_ID
   */
  function GetItemPreview
  (
    $Item_ID
  )
  {
    global $Item_Class, $User_Data, $Pokemon_Service; // Pokemon_Service for GetPokemonData

    $Item_Data = $Item_Class->FetchItemData($Item_ID);
    if (!$Item_Data) return "Error: Item data not found.";


    $Slot_Text = '';
    if ( $User_Data['Roster'] )
    {
      for ( $i = 0; $i < 6; $i++ )
      {
        if ( isset($User_Data['Roster'][$i]['ID']) )
        {
          $Pokemon = $Pokemon_Service->GetPokemonData($User_Data['Roster'][$i]['ID']); // Updated call

          if ( !$Pokemon['Item'] )
          {
            $Slot_Text .= "
              <td colspan='1' style='width: calc(100% / 6);'>
                <img src='{$Pokemon['Icon']}' onclick=\"EquipItem({$Item_Data['ID']}, {$Pokemon['ID']});\" />
              </td>
            ";
          }
          else
          {
            $Slot_Text .= "
              <td colspan='1' style='width: calc(100% / 6);'>
                <img src='{$Pokemon['Icon']}' style='filter: grayscale(100%);' />
              </td>
            ";
          }
        }
        else
        {
          $Pokemon_Icon = DOMAIN_SPRITES . "/Pokemon/Sprites/0_mini.png"; // Default icon

          $Slot_Text .= "
            <td colspan='1' style='width: calc(100% / 6);'>
              <img src='{$Pokemon_Icon}' style='filter: grayscale(100%);' />
            </td>
          ";
        }
      }
    }
    else
    {
      for ( $i = 0; $i < 6; $i++ )
      {
        $Slot_Text .= "
          <td colspan='1' style='width: calc(100% / 6);'>
            <img src='" . DOMAIN_SPRITES . "/Pokemon/Sprites/0.png' style='filter: grayscale(100%);' />
          </td>
        ";
      }
    }

    return "
      <tr>
        <td colspan='3'>
          <img src='{$Item_Data['Icon']}' />
        </td>
        <td colspan='3'>
          <b>{$Item_Data['Name']}</b>
        </td>
      </tr>
      <tr>
        <td colspan='6' style='padding: 5px;'>
          {$Item_Data['Description']}
        </td>
      </tr>
      <tr>
        {$Slot_Text}
      </tr>
    ";
  }

  /**
   * Equip the specified item to the specified Pokemon.
   *
   * @param $Item_ID
   * @param $Pokemon_ID
   */
  function EquipItem
  (
    $Item_ID,
    $Pokemon_ID
  )
  {
    global $Item_Class, $User_Data, $Pokemon_Service; // Pokemon_Service for GetPokemonData

    $Item_Data = $Item_Class->FetchOwnedItem($User_Data['ID'], $Item_ID);
    $Poke_Data = $Pokemon_Service->GetPokemonData($Pokemon_ID); // Updated call

    if ( !$Item_Data || $Item_Data['Quantity'] < 1 )
    {
      return [
        'Success' => false,
        'Message' => 'You do not own enough of this item to attach it to a Pok&eacute;mon.'
      ];
    }

    if ( !$Poke_Data || $Poke_Data['Owner_Current'] != $User_Data['ID'] )
    {
      return [
        'Success' => false,
        'Message' => 'You may not attach an item to a Pok&eacute;mon that does not belong to you.'
      ];
    }

    $Attach_Item = $Item_Class->Attach($Item_ID, $Pokemon_ID, $User_Data['ID']);

    if ( $Attach_Item )
    {
      return [
        'Success' => true,
        'Message' => "You have attached a {$Item_Data['Name']} to your {$Poke_Data['Display_Name']}."
      ];
    }

    return [
      'Success' => false,
      'Message' => 'An error occurred while attaching the item.' // Generic error if Attach fails for other reasons
    ];
  }

  /**
   * Unequip the item of the specified Pokemon.
   *
   * @param $Pokemon_ID
   */
  function UnequipItem
  (
    $Pokemon_ID
  )
  {
    global $Item_Class, $User_Data, $Pokemon_Service; // Pokemon_Service for GetPokemonData

    $Pokemon_Info = $Pokemon_Service->GetPokemonData($Pokemon_ID); // Updated call
    if ( !$Pokemon_Info || $Pokemon_Info['Owner_Current'] != $User_Data['ID'] )
    {
      return [
        'Success' => false,
        'Message' => 'This Pok&eacute;mon does not belong to you.'
      ];
    }

    $Remove_Item = $Item_Class->Unequip($Pokemon_Info['ID'], $User_Data['ID']);

    return [
      'Success' => $Remove_Item['Type'] == 'success' ? true : false,
      'Message' => $Remove_Item['Message']
    ];
  }
