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
        $Errors_Upload = "";
        $Validated_Mime_Type = null;

        // Check file size first (1MB limit)
        if ( $Avatar_File['size'] > 1024000 ) {
          $Errors_Upload .= "<div><b style='color: #ff0000;'>Submitted avatars must be less than 1MB in size.</b></div>";
        }

        // Server-side MIME type validation
        if (empty($Errors_Upload)) {
          $finfo = finfo_open(FILEINFO_MIME_TYPE);
          $mime_type = finfo_file($finfo, $Avatar_File['tmp_name']);
          finfo_close($finfo);
          $allowed_mime_types = ['image/png', 'image/jpeg', 'image/gif'];
          if (!in_array($mime_type, $allowed_mime_types, true)) {
            $Errors_Upload .= "<div><b style='color: #ff0000;'>Invalid file type. Allowed types: PNG, JPEG, GIF. (MIME)</b></div>";
          } else {
            $Validated_Mime_Type = $mime_type; // Store validated MIME for GD processing
          }
        }

        // Strict Extension Validation
        if (empty($Errors_Upload)) {
          $filename = $Avatar_File['name'];
          $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
          $allowed_extensions = ['png', 'jpg', 'jpeg', 'gif'];
          if (!in_array($extension, $allowed_extensions, true)) {
            $Errors_Upload .= "<div><b style='color: #ff0000;'>Invalid file extension. Allowed extensions: .png, .jpg, .jpeg, .gif.</b></div>";
          }
        }

        // Image dimensions check (using getimagesize, also confirms it's an image)
        if (empty($Errors_Upload)) {
          $Avatar_Metadata = getimagesize($Avatar_File["tmp_name"]);
          if ( !$Avatar_Metadata ) {
            $Errors_Upload .= "<div><b style='color: #ff0000;'>Uploaded file is not a valid image or is corrupted.</b></div>";
          } else {
            if ( $Avatar_Metadata[0] > 200 || $Avatar_Metadata[1] > 200 ) {
              $Errors_Upload .= "<div><b style='color: #ff0000;'>Image dimensions must not exceed 200x200 pixels.</b></div>";
            }
          }
        }

        if ( !empty($Errors_Upload) ) {
          $Text = $Errors_Upload;
        } else {
          // Image Re-processing using GD
          $source_image = null;
          switch ($Validated_Mime_Type) {
            case 'image/jpeg':
              $source_image = @imagecreatefromjpeg($Avatar_File['tmp_name']);
              break;
            case 'image/png':
              $source_image = @imagecreatefrompng($Avatar_File['tmp_name']);
              break;
            case 'image/gif':
              $source_image = @imagecreatefromgif($Avatar_File['tmp_name']);
              break;
          }

          if (!$source_image) {
            $Text = "<div><b style='color: #ff0000;'>Failed to process image. The file may be corrupted or an unsupported image format.</b></div>";
          } else {
            // Always save as PNG for consistency and to strip potential issues
            $New_Filename_Base = $Clan_Data['ID']; // Use Clan ID for filename
            $New_Filepath_Relative = '/Avatars/Clan/' . $New_Filename_Base . '.png';
            $Destination_Full_Path = dirname(__FILE__, 4) . '/images' . $New_Filepath_Relative;

            // Ensure target directory exists (it should, but good practice)
            $clan_avatar_dir = dirname($Destination_Full_Path);
            if (!is_dir($clan_avatar_dir)) {
                mkdir($clan_avatar_dir, 0755, true);
            }

            // Re-save the image as PNG
            if (imagepng($source_image, $Destination_Full_Path)) {
              imagedestroy($source_image);
              try {
                $Update_Avatar = $pdo_instance->prepare("UPDATE `clans` SET `Avatar` = ? WHERE `ID` = ? LIMIT 1");
                $Update_Avatar->execute([ $New_Filepath_Relative, $Clan_Data['ID'] ]);
                $Text = "<div><b style='color: #00ff00;'>Clan avatar uploaded and updated successfully!</b></div>";
                // Update $New_Filepath to be the relative path for the JSON response
                $New_Filepath = $New_Filepath_Relative;
              } catch ( PDOException $e ) {
                HandleError($e);
                $Text = "<div><b style='color: #ff0000;'>Database error during avatar update. Avatar file was processed but not saved to profile.</b></div>";
                // Optionally delete the processed file if DB update fails: if (file_exists($Destination_Full_Path)) unlink($Destination_Full_Path);
                $New_Filepath = null; // Prevent using this path in JSON response if DB fails
              }
            } else {
              imagedestroy($source_image);
              $Text = "<div><b style='color: #ff0000;'>Failed to save processed image.</b></div>";
              $New_Filepath = null;
            }
          }
        }
      } else {
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
