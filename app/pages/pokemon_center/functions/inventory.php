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
    $Inventory_Items = $Item_Service->GetUserInventoryByCategory($User_Data['ID'], $Inventory_Tab);
    if (is_array($Inventory_Items)) {
      foreach ($Inventory_Items as $Key => $Item) {
        // Assuming Item_Name and Icon are fields that need escaping for HTML in JS
        if (isset($Item['Item_Name'])) {
          $Inventory_Items[$Key]['Item_Name'] = htmlspecialchars($Item['Item_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        // Icon path from Item_Name, JS constructs full path. Item_Name itself is used.
        // If Item_Icon field exists and is a full path, it should be escaped.
        // Current JS uses Item_Name to construct path: /images/Items/${Item.Item_Name}.png
        // So Item_Name needs to be safe for URL path component and for display.
        // htmlspecialchars on Item_Name already helps.
      }
    }
    return $Inventory_Items;
  }

  /**
   * Get the user's items that are currently equipped to Pokemon.
   * Returns data pre-escaped for safe HTML rendering by client-side JavaScript.
   */
  function GetEquippedItems()
  {
    global $Pokemon_Service, $Item_Class, $User_Data;

    $Equipped_Pokemon_With_Items = $Pokemon_Service->GetPokemonWithItems($User_Data['ID']);

    $Formatted_Equipped_Items = [];
    if ( !empty($Equipped_Pokemon_With_Items) )
    {
      foreach ( $Equipped_Pokemon_With_Items as $Pokemon_With_Item )
      {
        $Item_Info = $Item_Class->FetchItemData($Pokemon_With_Item['Item_ID']);

        if ($Item_Info)
        {
          $Formatted_Equipped_Items[] = [
            'Pokemon' => [
              'ID' => $Pokemon_With_Item['ID'],
              'Name' => htmlspecialchars($Pokemon_With_Item['Display_Name'] ?? 'Unknown Pok&eacute;mon', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
              'Icon' => htmlspecialchars($Pokemon_With_Item['Icon'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ],
            'Item' => [
              'ID' => $Item_Info['ID'],
              'Name' => htmlspecialchars($Item_Info['Name'] ?? 'Unknown Item', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
              'Icon' => htmlspecialchars($Item_Info['Icon'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')
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
          $Pokemon = $Pokemon_Service->GetPokemonData($User_Data['Roster'][$i]['ID']);
          $Pokemon_Icon_Escaped = htmlspecialchars($Pokemon['Icon'] ?? (DOMAIN_SPRITES . "/Pokemon/Sprites/0_mini.png"), ENT_QUOTES | ENT_HTML5, 'UTF-8');

          if ( !$Pokemon['Item'] )
          {
            $Slot_Text .= "
              <td colspan='1' style='width: calc(100% / 6);'>
                <img src='{$Pokemon_Icon_Escaped}' onclick=\"EquipItem({$Item_Data['ID']}, {$Pokemon['ID']});\" />
              </td>
            ";
          }
          else
          {
            $Slot_Text .= "
              <td colspan='1' style='width: calc(100% / 6);'>
                <img src='{$Pokemon_Icon_Escaped}' style='filter: grayscale(100%);' />
              </td>
            ";
          }
        }
        else
        {
          $Pokemon_Icon_Escaped = htmlspecialchars(DOMAIN_SPRITES . "/Pokemon/Sprites/0_mini.png", ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Default icon

          $Slot_Text .= "
            <td colspan='1' style='width: calc(100% / 6);'>
              <img src='{$Pokemon_Icon_Escaped}' style='filter: grayscale(100%);' />
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

    $Item_Icon_Escaped = htmlspecialchars($Item_Data['Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $Item_Name_Escaped = htmlspecialchars($Item_Data['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $Item_Description_Escaped = nl2br(htmlspecialchars($Item_Data['Description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return "
      <tr>
        <td colspan='3'>
          <img src='{$Item_Icon_Escaped}' alt='{$Item_Name_Escaped}' />
        </td>
        <td colspan='3'>
          <b>{$Item_Name_Escaped}</b>
        </td>
      </tr>
      <tr>
        <td colspan='6' style='padding: 5px;'>
          {$Item_Description_Escaped}
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
      $Item_Name_Escaped_Msg = htmlspecialchars($Item_Data['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $Poke_Display_Name_Escaped_Msg = htmlspecialchars($Poke_Data['Display_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      return [
        'Success' => true,
        'Message' => "You have attached a <b>{$Item_Name_Escaped_Msg}</b> to your <b>{$Poke_Display_Name_Escaped_Msg}</b>."
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
