<?php
  require_once '../../required/session.php';
  // require_once '../../classes/shop.php'; // Shop_Class is already instantiated in session.php

  if ( isset($_GET['Object_ID']) )
    $Object_ID = (int)($_GET['Object_ID']); // Cast to int
  else
    $Object_ID = null;

  if ( isset($_GET['Object_Type']) )
    $Object_Type = Purify($_GET['Object_Type']); // String, Purify is okay
  else
    $Object_Type = null;

  if ( !$Object_ID || !$Object_Type )
  {
    echo "
      <div class='error'>
        An error occurred while fetching object data (Invalid Object ID or Type).
      </div>
    ";
  }
  else if ( !in_array($Object_Type, ['Item', 'Pokemon']) )
  {
    echo "
      <div class='error'>
        An error occurred while fetching object data (Invalid Object Type specified).
      </div>
    ";
  }
  else
  {
    // FetchObjectData is already part of $Shop_Class which uses injected PDO.
    // It now returns a single row or false.
    $Object_Data_Row = $Shop_Class->FetchObjectData($Object_ID, $Object_Type);

    if ( !$Object_Data_Row )
    {
      echo "
        <div class='error'>
          An error occurred while fetching object data (Object not found).
        </div>
      ";
    }
    else
    {
      // $Object_Data_Row is a single associative array here
      if ( $Object_Data_Row['Remaining'] < 1 )
        echo "Out of stock!";
      else
        echo "In stock: " . number_format($Object_Data_Row['Remaining']);
    }
  }
