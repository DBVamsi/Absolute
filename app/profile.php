<?php
  require_once 'core/required/layout_top.php'; // Includes session.php where services are instantiated

  // Determine Profile ID
  $Profile_ID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ( $Profile_ID === 0 && isset($User_Data['ID']) ) // If no ID in GET, default to logged in user
  {
    $Profile_ID = $User_Data['ID'];
  }
  else if ( $Profile_ID === 0 )
  {
    // No ID in GET and no user logged in, or invalid ID format
    $Profile_User = false;
  }

  if ($Profile_ID !== 0) { // Fetch data only if Profile_ID is determined
      $Profile_User = $User_Class->FetchUserData($Profile_ID);
  } else {
      $Profile_User = false; // Ensure $Profile_User is false if no valid ID
  }


  // All other necessary variables like $User_Class, $Clan_Class, $Constants, $DOMAIN_ROOT, $DOMAIN_SPRITES
  // are expected to be available globally from layout_top.php (which includes session.php).

  if ( $Profile_User )
  {
    // Data is fetched, now include the view.
    // The view will use $Profile_User, $User_Class, $Constants, etc.
    require_once 'app/views/profile/show_view.php';
  }
  else
  {
    // Original error display for nonexistent user
?>
<div class='panel content'>
  <div class='head'>
    Profile
  </div>
  <div class='body' style='padding: 5px;'>
    <div class='error' style='margin-bottom: 0;'>
      You are attempting to view the profile of a nonexistent user or an invalid ID was provided.
    </div>
  </div>
</div>
<?php
  }

  require_once 'core/required/layout_bottom.php';
?>
