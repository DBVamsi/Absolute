<?php
  require_once '../../required/session.php';

  if ( !isset($_POST['Tab']) )
  {
    echo "
      <br />
      <div class='error'>
        An error occurred while processing your input.<br />
        Please try again.
      </div>
    ";

    return;
  }

  $allowed_tabs = ['Pokemon', 'Trainer'];
  $Tab_Input = $_POST['Tab'] ?? 'Pokemon'; // Default to Pokemon

  if (!in_array($Tab_Input, $allowed_tabs, true)) {
    $Tab = 'Pokemon'; // Default to a safe value if input is not allowed
  } else {
    $Tab = $Tab_Input;
  }

  $Current_Page = isset($_POST['Page']) ? (int)$_POST['Page'] : 1;
  if ($Current_Page < 1) $Current_Page = 1;
  $Display_Limit = 20;

  $Begin = ($Current_Page - 1) * $Display_Limit;
  if ( $Begin < 0 )
    $Begin = 1;

  /**
   * Construct the correct SQL query for the active tab.
   * Defaults to the Pokemon tab.
   */
  switch($Tab)
  {
    case 'Pokemon':
      $Rankings_Query = "SELECT `ID` FROM `pokemon` ORDER BY `Experience` DESC";
      $Rankings_Parameters = [];

      $First_Place_Query = "SELECT `ID` FROM `pokemon` ORDER BY `Experience` DESC LIMIT 1";
      $First_Place_Parameters = [];

      break;

    case 'Trainer':
      $Rankings_Query = "SELECT `id` FROM `users` ORDER BY `TrainerExp` DESC";
      $Rankings_Parameters = [];

      $First_Place_Query = "SELECT `id` FROM `users` ORDER BY `TrainerExp` DESC LIMIT 1";
      $First_Place_Parameters = [];

      break;

    default:
      $Rankings_Query = "SELECT `ID` FROM `pokemon` ORDER BY `Experience` DESC";
      $Rankings_Parameters = [];

      $First_Place_Query = "SELECT `ID` FROM `pokemon` ORDER BY `Experience` DESC LIMIT 1";
      $First_Place_Parameters = [];

      break;
  }

  /**
   * Perform the database queries.
   */
  $Fetch_Rankings = $PDO->prepare($Rankings_Query . " LIMIT " . $Begin . "," . $Display_Limit);
  $Fetch_Rankings->execute($Rankings_Parameters);
  $Fetch_Rankings->setFetchMode(PDO::FETCH_ASSOC);
  $Rankings = $Fetch_Rankings->fetchAll();

  $Fetch_First_Place = $PDO->prepare($First_Place_Query);
  $Fetch_First_Place->execute($First_Place_Parameters);
  $Fetch_First_Place->setFetchMode(PDO::FETCH_ASSOC);
  $First_Place = $Fetch_First_Place->fetch();

  /**
   * Given the current tab, fetch the first place data.
   * Defaults to the Pokemon tab.
   */
  switch($Tab)
  {
    case 'Pokemon':
      $First_Place = GetPokemonData($First_Place['ID']);
      $First_Place_User = $User_Class->DisplayUserName($First_Place['Owner_Current'], false, true, true);
      break;

    case 'Trainer':
      $First_Place = $User_Class->FetchUserData($First_Place['id']);
      $First_Place_User = $User_Class->DisplayUserName($First_Place['ID'], false, true, true);
      break;

    default:
      $First_Place = GetPokemonData($First_Place['ID']);
      $First_Place_User = $User_Class->DisplayUserName($First_Place['Owner_Current'], false, true, true);
      break;
  }
?>

<div style='flex-basis: 100%; width: 100%;'>
  <h3><?= htmlspecialchars($Tab, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?> Rankings</h3>
</div>

<table class='border-gradient' style='margin: 5px auto; flex-basis: 35%; width: 35%;'>
  <thead>
    <th colspan='3'>
      <b><?= htmlspecialchars((isset($First_Place['Display_Name']) ? $First_Place['Display_Name'] : ($First_Place['Username'] ?? 'N/A')), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></b>
    </th>
  </thead>
  <tbody>
    <tr>
      <td colspan='1' rowspan='2' style='width: 100px;'>
        <img src='<?= htmlspecialchars((isset($First_Place['Sprite']) ? $First_Place['Sprite'] : ($First_Place['Avatar'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>' />
      </td>
      <td colspan='2'>
        <?php
          // $First_Place_User is already HTML from DisplayUserName, $First_Place['Display_Name'] needs escaping
          $first_place_name_display = isset($First_Place['Display_Name']) ? htmlspecialchars($First_Place['Display_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : $First_Place_User;
        ?>
        <b><?= $first_place_name_display; ?></b>
        <?= (isset($First_Place['Nickname']) ? "<br />( <i>" . htmlspecialchars($First_Place['Nickname'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</i> )" : '') ?>
      </td>
    </tr>
    <tr>
      <td colspan='2'>
        <b>Level</b>: <?= htmlspecialchars((isset($First_Place['Level']) ? $First_Place['Level'] : ($First_Place[$Tab . '_Level'] ?? 'N/A')), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
        <br />
        <b>Experience</b>: <?= htmlspecialchars((isset($First_Place['Experience']) ? $First_Place['Experience'] : ($First_Place[$Tab . '_Exp'] ?? 'N/A')), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
      </td>
    </tr>
    <tr>
      <td colspan='3' style='padding: 5px;'>
        <?php
          switch($Tab)
          {
            case 'Pokemon':
              echo "<b>Current Owner</b> {$First_Place_User}"; // $First_Place_User is HTML
              break;

            case 'Trainer':
              echo htmlspecialchars($First_Place['Status'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8');
              break;

            default:
              echo "<b>Current Owner</b> {$First_Place_User}"; // $First_Place_User is HTML
              break;
          }
        ?>
      </td>
    </tr>
  </tbody>
</table>

<table class='border-gradient' style='margin: 5px auto; flex-basis: 70%; width: 700px;'>
  <tbody>
    <?php
      Pagination(str_replace('SELECT `ID`', 'SELECT COUNT(*)', $Rankings_Query), $Rankings_Parameters, $User_Data['ID'], $Current_Page, $Display_Limit, 5, "onclick='Update_Page([PAGE]);'");
    ?>
  </tbody>
  <tbody>
    <?php
      foreach ( $Rankings as $Rank_Key => $Rank_Val )
      {
        if ( $Rank_Key + $Begin === 0 )
          continue;

        $Rank_Key++;

        if ( $Tab === 'Pokemon' )
        {
          $Poke_Rank_Data = GetPokemonData($Rank_Val['ID']);
          $Username = $User_Class->DisplayUserName($Poke_Rank_Data['Owner_Current'], false, false, true);
        }
        else
        {
          $User_Rank_Data = $User_Class->FetchUserData($Rank_Val['id']);
          $Username = $User_Class->DisplayUserName($Rank_Val['id'], false, false, true);
        }

        $Sprite_Escaped = htmlspecialchars((isset($Poke_Rank_Data) ? $Poke_Rank_Data['Icon'] : ($User_Rank_Data['Avatar'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // $Username is from DisplayUserName, already HTML. $Poke_Rank_Data['Display_Name'] needs escape.
        $Display_Name_Escaped = (isset($Poke_Rank_Data) ? htmlspecialchars($Poke_Rank_Data['Display_Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : $Username);
        $Nickname_Escaped = (isset($Poke_Rank_Data) && !empty($Poke_Rank_Data['Nickname'])) ? "<br /><i>" . htmlspecialchars($Poke_Rank_Data['Nickname'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</i>" : '';
        $Level_Escaped = htmlspecialchars((isset($Poke_Rank_Data) ? $Poke_Rank_Data['Level'] : ($User_Rank_Data['Trainer_Level'] ?? 'N/A')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $Experience_Escaped = htmlspecialchars((isset($Poke_Rank_Data) ? $Poke_Rank_Data['Experience'] : ($User_Rank_Data['Trainer_Exp'] ?? 'N/A')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // $Username for owner is already HTML. Static link is fine.
        $Owner_Display_Escaped = (isset($Poke_Rank_Data) ? $Username : "<a href='javascript:void(0);'>Battle User</a>"); // Link could be more specific if needed

        echo "
          <tr>
            <td colspan='5' style='width: 50px;'>
              #" . htmlspecialchars(($Rank_Key + $Begin), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "
            </td>
            <td colspan='5' style='width: 100px;'>
              <img src='{$Sprite_Escaped}' />
            </td>
            <td colspan='9' style='width: 150px;'" . ($Tab === 'Pokemon' ? " data-src='" . htmlspecialchars(DOMAIN_ROOT . "/core/ajax/pokemon.php?id=" . ((int)$Rank_Val['ID']), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "' class='popup'" : '') . ">
                {$Display_Name_Escaped}
                {$Nickname_Escaped}
            </td>
            <td colspan='9' style='width: 150px;'>
              Level: {$Level_Escaped}
              <br />
              Exp: {$Experience_Escaped}
            </td>
            <td colspan='7' style='width: 150px;'>
              {$Owner_Display_Escaped}
            </td>
          </tr>
        ";
      }
    ?>
  </tbody>
</table>

<script type='text/javascript'>
  [].forEach.call(document.getElementsByClassName("popup"), function(el) {
    el.lightbox = new IframeLightbox(el, {
      scrolling: false,
      rate: 500,
      touch: false,
    });
  });
</script>
