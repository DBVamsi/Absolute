<?php
  // This view expects $Profile_User, $User_Class, $Constants, $DOMAIN_ROOT, $DOMAIN_SPRITES to be defined by the controller (profile.php)
  // Ensure all dynamic output is escaped with htmlspecialchars()
?>
<div class='panel content'>
  <div class='head'>
    <?= htmlspecialchars($Profile_User['Username'], ENT_QUOTES, 'UTF-8'); ?>'s Profile
  </div>
  <div class='body' style='padding: 5px;'>
    <div class='flex'>
      <div style='flex-basis: 350px; margin-right: 5px;'>
        <table class='border-gradient' style='width: 350px;'>
          <thead></thead>
          <tbody>
            <tr>
              <td colspan='4'>
                <?= ( $Profile_User['Avatar'] ? "<img src='" . htmlspecialchars($Profile_User['Avatar'], ENT_QUOTES, 'UTF-8') . "' />" : 'This user has no avatar set.' ); ?>
              </td>
            </tr>
          </tbody>
          <tbody>
            <tr>
              <td colspan='4'>
                <?= $User_Class->DisplayUserRank($Profile_User['ID']); // Assumes DisplayUserRank returns safe HTML ?>
              </td>
            </tr>

            <thead>
              <tr>
                <td colspan='4'>
                  <b>Activity Information</b>
                </td>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td colspan='2'>
                  <b>Joined On</b>
                </td>
                <td colspan='2'>
                  <?= htmlspecialchars(date("F j, Y (g:i A)", $Profile_User['Date_Registered']), ENT_QUOTES, 'UTF-8'); ?>
                </td>
              </tr>
              <tr>
                <td colspan='2'>
                  <b>Playtime</b>
                </td>
                <td colspan='2'>
                  <?= htmlspecialchars($Profile_User['Playtime'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
              </tr>
              <tr>
                <td colspan='2'>
                  <b>Last Online</b>
                </td>
                <td colspan='2'>
                  <?= htmlspecialchars(LastSeenDate($Profile_User['Last_Active'], 'week'), ENT_QUOTES, 'UTF-8'); // Assumes LastSeenDate returns safe text ?>
                </td>
              </tr>
              <tr>
                <td colspan='2'>
                  <b>Visiting Page</b>
                </td>
                <td colspan='2'>
                  <?= htmlspecialchars($Profile_User['Last_Page'], ENT_QUOTES, 'UTF-8'); ?>
                </td>
              </tr>
            </tbody>

            <thead>
              <tr>
                <th colspan='4'>
                  Currencies
                </th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <?php
                  foreach ( $Constants->Currency as $Currency )
                  {
                    echo "
                      <td colspan='2' style='width: 175px;'>
                        <img src='" . htmlspecialchars($Currency['Icon'], ENT_QUOTES, 'UTF-8') . "' />
                      </td>
                    ";
                  }
                ?>
              </tr>
              <tr>
                <?php
                  foreach ( $Constants->Currency as $Currency )
                  {
                    echo "
                      <td colspan='2'>
                        " . htmlspecialchars(number_format($Profile_User[$Currency['Value']]), ENT_QUOTES, 'UTF-8') . "
                      </td>
                    ";
                  }
                ?>
              </tr>
            </tbody>
          </tbody>
        </table>

        <table class='border-gradient' style='margin-top: 5px; width: 350px;'>
          <thead>
            <tr>
              <th colspan='4'>
                Interactions
              </th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan='2' style='width: 50%;'>
                <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/direct_messages.php?Message_Recipient=" . $Profile_User['ID'], ENT_QUOTES, 'UTF-8'); ?>'>
                  Message <?= htmlspecialchars($Profile_User['Username'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
              <td colspan='2' style='width: 50%;'>
                <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/trades.php?Action=Create&ID=" . $Profile_User['ID'], ENT_QUOTES, 'UTF-8'); ?>'>
                  Trade With <?= htmlspecialchars($Profile_User['Username'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
            </tr>
            <tr>
              <td colspan='2' style='width: 50%;'>
                <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/battle_create.php?Battle_Type=Trainer&Foe=" . $Profile_User['ID'], ENT_QUOTES, 'UTF-8'); ?>'>
                  Battle <?= htmlspecialchars($Profile_User['Username'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
              <td colspan='2' style='width: 50%;'>
                <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/report.php?Reporting_User=" . $Profile_User['ID'], ENT_QUOTES, 'UTF-8'); ?>'>
                  Report <?= htmlspecialchars($Profile_User['Username'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div style='flex: 1;'>
        <table class='border-gradient' style='width: calc(100% - 5px);'>
          <tbody>
            <tr>
              <td colspan='1' style='width: 25%;' onclick="HandleTab('roster');">
                <b>Roster</b>
              </td>
              <td colspan='1' style='width: 25%;' onclick="HandleTab('box');">
                <b>Box</b>
              </td>
              <td colspan='1' style='width: 25%;' onclick="HandleTab('inventory');">
                <b>Inventory</b>
              </td>
              <td colspan='1' style='width: 25%;' onclick="HandleTab('stats');">
                <b>Stats</b>
              </td>
            </tr>
          </tbody>
        </table>

        <table class='border-gradient' id='ProfileAJAX' style='margin-top: 5px; width: calc(100% - 5px);'>
          <tbody>
            <tr>
              <td>
                Loading
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script type='text/javascript'>
  window.onload = () =>
  {
    HandleTab('roster');
  }

  const HandleTab = (Tab) =>
  {
    if ( !Tab )
      return;

    return new Promise((resolve, reject) =>
    {
      const req = new XMLHttpRequest();
      req.open('GET', `<?= htmlspecialchars(DOMAIN_ROOT . "/core/ajax/profile/", ENT_QUOTES, 'UTF-8'); ?>${Tab}.php?User_ID=<?= $Profile_User['ID']; ?>`);
      req.send(null);
      req.onerror = (error) => reject(Error(`Network Error: ${error}`));
      req.onload = () =>
      {
        if ( req.status === 200 )
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.responseText;
          resolve(req.response);
        }
        else
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.statusText;
          reject(Error(req.statusText))
        }
      };
    });
  }

  const UpdateBox = (Page) =>
  {
    if ( !Page )
      return;

    return new Promise((resolve, reject) =>
    {
      const req = new XMLHttpRequest();
      req.open('GET', `<?= htmlspecialchars(DOMAIN_ROOT . "/core/ajax/profile/box.php?User_ID=" . $Profile_User['ID'], ENT_QUOTES, 'UTF-8'); ?>&Page=${Page}`);
      req.send(null);
      req.onerror = (error) => reject(Error(`Network Error: ${error}`));
      req.onload = () =>
      {
        if ( req.status === 200 )
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.responseText;
          resolve(req.response);
        }
        else
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.statusText;
          reject(Error(req.statusText))
        }
      };
    });
  }

  const UpdateInventory = (User_ID, Page) =>
  {
    if ( !User_ID || !Page )
      return;

    return new Promise((resolve, reject) =>
    {
      const req = new XMLHttpRequest();
      req.open('GET', `<?= htmlspecialchars(DOMAIN_ROOT . "/core/ajax/profile/inventory.php?User_ID=", ENT_QUOTES, 'UTF-8'); ?>${User_ID}&Category=${Page}`);
      req.send(null);
      req.onerror = (error) => reject(Error(`Network Error: ${error}`));
      req.onload = () =>
      {
        if ( req.status === 200 )
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.responseText;
          resolve(req.response);
        }
        else
        {
          document.querySelector('#ProfileAJAX').innerHTML = req.statusText;
          reject(Error(req.statusText))
        }
      };
    });
  }
</script>
