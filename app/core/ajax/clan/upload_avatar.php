<?php
  require_once '../../required/session.php';

  $Clan_Data = $Clan_Class->FetchClanData($User_Data['Clan']); // Assumes $Clan_Class is available via session.php

  $Error = false;
  $Text = ''; // Initialize Text

  if ( !$Clan_Data )
  {
    $Error = true;
    $Text = "
      <div>
        <b style='color: #ff0000;'>
          You must be in a clan to use this feature.
        </b>
      </div>
    ";
  }
  // This logic seems inverted. It should be an error IF the user is just a 'Member'.
  // Or, more clearly, if their rank is NOT Moderator or Administrator (or whatever higher ranks exist)
  // For now, fixing the Purify call. Logic of permission check might need review.
  else if ( $User_Data['Clan_Rank'] == 'Member' )
  {
    $Error = true;
    $Text = "
      <div>
        <b style='color: #ff0000;'>
          You must be at least a Clan Moderator to use this feature.
        </b>
      </div>
    ";
  }

  // Only proceed with file upload if no initial errors based on clan status/rank
  if ( !$Error )
  {
    if ( isset($_FILES['avatar']) )
    {
      // Directly use $_FILES['avatar'] elements after validation. Do not Purify the $_FILES array itself.
      $Avatar_File = $_FILES['avatar'];

      if ( file_exists($Avatar_File['tmp_name']) && is_uploaded_file($Avatar_File['tmp_name']) )
      {
        $Avatar_Metadata = getimagesize($Avatar_File["tmp_name"]);
        $Errors_Upload = null; // Use a different variable name for upload specific errors

        if ( !$Avatar_Metadata ) // Check if getimagesize failed (not a valid image)
        {
          $Errors_Upload .= "
            <div>
              <b style='color: #ff0000;'>
                Uploaded file is not a valid image.
              </b>
            </div>
          ";
        }
        else
        {
          if ( $Avatar_Metadata[0] > 200 || $Avatar_Metadata[1] > 200 )
          {
            $Errors_Upload .= "
              <div>
                <b style='color: #ff0000;'>
                  Your sprite exceeds the allowed size dimensions (200x200).
                </b>
              </div>
            ";
          }

          if ( !in_array($Avatar_File['type'], ['image/png', 'image/jpeg', 'image/jpg']) ) // Added image/jpg
          {
            $Errors_Upload .= "
              <div>
                <b style='color: #ff0000;'>
                  You must submit either a file that has the .png or .jpg extension.
                </b>
              </div>
            ";
          }

          if ( $Avatar_File['size'] > 1024000 ) // 1MB
          {
            $Errors_Upload .= "
              <div>
                <b style='color: #ff0000;'>
                  Submitted avatars must be less than 1MB in size.
                </b>
              </div>
            ";
          }
        }

        if ( $Errors_Upload )
        {
          $Text = $Errors_Upload;
        }
        else
        {
          $New_Filepath = '/Avatars/Clan/' . $Clan_Data['ID'] . '.png'; // Consider making extension dynamic or converting to PNG

          try
          {
            // $PDO is available via $this->pdo in $Clan_Class if methods are called on an instance.
            // However, this script uses $PDO directly. This implies $pdo_instance from session.php should be used.
            // For now, assuming $PDO is correctly representing $pdo_instance from session.php.
            // This would be a point of failure if $PDO is not the $pdo_instance.
            // Correct would be: $Clan_Class->UpdateAvatarPath($Clan_Data['ID'], $New_Filepath); (if such method exists)
            // Or direct use of $pdo_instance if this script is not part of a class.
            $Update_Avatar = $pdo_instance->prepare("UPDATE `clans` SET `Avatar` = ? WHERE `ID` = ? LIMIT 1");
            $Update_Avatar->execute([ $New_Filepath, $Clan_Data['ID'] ]);
          }
          catch ( PDOException $e )
          {
            HandleError($e);
            // It's better to set an error message than to just die or do nothing.
            $Text = "<div><b style='color: #ff0000;'>Database error during avatar update.</b></div>";
            // To prevent move_uploaded_file if DB fails:
            $New_Filepath = null; // Clear New_Filepath to prevent using it later if DB failed
          }

          if ($New_Filepath) { // Only move if DB was successful (or if we decide to move anyway and log DB error)
            if (move_uploaded_file($Avatar_File['tmp_name'], dirname(__FILE__, 4) . '/images' . $New_Filepath))
            {
              $Text = "
                <div>
                  <b style='color: #00ff00;'>
                    The avatar that you have submitted has been uploaded!
                  </b>
                </div>
              ";
            } else {
              $Text = "<div><b style='color: #ff0000;'>Failed to move uploaded file.</b></div>";
              // Potentially revert DB change if file move fails, though this adds complexity.
            }
          }
        }
      }
      else
      {
        $Text = "<div><b style='color: #ff0000;'>File upload error or no file selected.</b></div>";
      }
    }
    else if (!$Error) // If no initial error but also no file, this means user didn't submit one.
    {
      $Text = "
        <div>
          <b style='color: #ff0000;'>
            You must upload an image for it to be processed.
          </b>
        </div>
      ";
    }
  }

  $Output = [
    'Text' => $Text,
    // Use Clan_Data Avatar if New_Filepath wasn't set (e.g. on initial error or failed upload)
    'Avatar' => ( isset($New_Filepath) && $New_Filepath ? DOMAIN_SPRITES . $New_Filepath : ($Clan_Data ? $Clan_Data['Avatar'] : '') ),
  ];

  header('Content-Type: application/json');
  echo json_encode($Output);
