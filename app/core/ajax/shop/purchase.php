<?php
  require_once '../../required/session.php';
  // require_once '../../classes/shop.php'; // Shop_Class is already instantiated in session.php

  if ( isset($_GET['Shop']) )
    $Shop_ID = (int)($_GET['Shop']); // Cast to int
  else
    $Shop_ID = 1;

  if ( isset($_GET['Object_ID']) )
    $Object_ID = (int)($_GET['Object_ID']); // Cast to int
  else
    $Object_ID = null;

  if ( isset($_GET['Object_Type']) )
    $Object_Type = Purify($_GET['Object_Type']); // String, Purify is okay
  else
    $Object_Type = null;

  $Shop = $Shop_Class->FetchShopData($Shop_ID);

  if ( !$Shop || !$Object_ID || !$Object_Type )
  {
    echo "
      <div class='error'>
        An error occurred while processing your purchase (Invalid Shop, Object ID, or Type).
      </div>
    ";
  }
  else if ( !in_array($Object_Type, ['Item', 'Pokemon']) )
  {
    echo "
      <div class='error'>
        An error occurred while processing your purchase (Invalid Object Type).
      </div>
    ";
  }
  else
  {
    // FetchObjectData is already part of $Shop_Class which uses injected PDO.
    // $Object_Data = $Shop_Class->FetchObjectData($Object_ID, $Object_Type);
    // This call is actually not needed because PurchaseObject refetches it.
    // For now, I will leave it as it doesn't break anything, but it's redundant.

    // PurchaseObject uses injected services now.
    $Purchase_Object = $Shop_Class->PurchaseObject($Object_ID, $Object_Type);

    if ( !$Purchase_Object )
    {
      echo "
        <div class='error'>
          An error occurred while processing your purchase. This could be due to insufficient funds, the item being out of stock, or another issue.
        </div>
      ";
    }
    else
    {
      // Check if $Purchase_Object is an array (for Pokemon) or boolean true (for Item)
      if ( is_array($Purchase_Object) )
      {
        if ( !empty($Purchase_Object['Shiny_Alert']) ) // Check if keys exist
        {
          echo '
            <script type="text/javascript">
              setTimeout(() => {
                alert("The Pokemon that you purchased was Shiny!");
              }, 1);
            </script>
          ';
        }

        if ( !empty($Purchase_Object['Ungendered_Alert']) )
        {
          echo '
            <script type="text/javascript">
              setTimeout(() => {
                alert("The Pokemon that you purchased was Ungendered!");
              }, 1);
            </script>
          ';
        }

        $Total_Stat = array_sum($Purchase_Object['Stats']);
        $Total_IV = array_sum($Purchase_Object['IVs']);

        echo "
          <table class='border-gradient' style='width: 475px;'>
            <tbody>
              <tr>
                <td colspan='2' rowspan='3'>
                  <img src='<?= htmlspecialchars($Purchase_Object['Sprite'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' />
                </td>
                <td></td>
                <td style='width: 39px;'>
                  <b>HP</b>
                </td>
                <td style='width: 39px;'>
                  <b>Att</b>
                </td>
                <td style='width: 39px;'>
                  <b>Def</b>
                </td>
                <td style='width: 39px;'>
                  <b>Sp.A</b>
                </td>
                <td style='width: 39px;'>
                  <b>Sp.D</b>
                </td>
                <td style='width: 39px;'>
                  <b>Spe</b>
                </td>
                <td style='width: 39px;'>
                  <b>Total</b>
                </td>
              </tr>
              <tr>
                <td><b>Base</b></td>
                <td>" . number_format($Purchase_Object['Stats'][0]) . "</td>
                <td>" . number_format($Purchase_Object['Stats'][1]) . "</td>
                <td>" . number_format($Purchase_Object['Stats'][2]) . "</td>
                <td>" . number_format($Purchase_Object['Stats'][3]) . "</td>
                <td>" . number_format($Purchase_Object['Stats'][4]) . "</td>
                <td>" . number_format($Purchase_Object['Stats'][5]) . "</td>
                <td>" . number_format($Total_Stat) . "</td>
              </tr>
              <tr>
                <td><b>IVs</b></td>
                <td>" . number_format($Purchase_Object['IVs'][0]) . "</td>
                <td>" . number_format($Purchase_Object['IVs'][1]) . "</td>
                <td>" . number_format($Purchase_Object['IVs'][2]) . "</td>
                <td>" . number_format($Purchase_Object['IVs'][3]) . "</td>
                <td>" . number_format($Purchase_Object['IVs'][4]) . "</td>
                <td>" . number_format($Purchase_Object['IVs'][5]) . "</td>
                <td>" . number_format($Total_IV) . "</td>
              </tr>
              <tr>
                <td colspan='10' style='padding: 5px;'>
                  <b>You have successfully purchased a(n) <?= htmlspecialchars($Purchase_Object['Display_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>.</b>
                </td>
              </tr>
            <tbody>
          </table>
        ";
      }
      else // Assuming $Purchase_Object was true for item purchase
      {
        // Need to fetch Item Name if we want to display it.
        // $Item_Class is available via session.php
        $Item_Info = $Item_Class->FetchItemData($Object_ID);
        $Item_Name_Display = $Item_Info ? $Item_Info['Name'] : 'item';
        $Item_Name_Display_Escaped = htmlspecialchars($Item_Name_Display, ENT_QUOTES | ENT_HTML5, 'UTF-8');
         echo "
          <div class='success'>
            You have successfully purchased a(n) {$Item_Name_Display_Escaped}.
          </div>
        ";
      }
    }
  }
