<?php // app/views/auth/register_view.php ?>

<div class="panel content" style="margin: 5px auto; max-width: 700px;">
	<div class='head'>Register An Account</div>
	<div class='body' style='padding: 10px;'>
		<div class='nav'>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/index.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Home</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/login.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Login</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/register.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Register</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/discord.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Discord</a></div>
		</div>

    <div class='description' style="margin-top:10px;">
      Please fill out the form below in order to begin your journey as a Pok&eacute;mon Trainer.
    </div>

    <?php if (!empty($view_feedback_message['text'])): ?>
      <div style="margin-top: 10px; padding: 8px; border-radius: 3px; color: white; background-color: <?= ($view_feedback_message['type'] == 'success' ? '#4CAF50' : '#f44336') ?>;">
        <?= htmlspecialchars($view_feedback_message['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <?php // Do not show form on successful registration if we want to prevent re-submission easily ?>
    <?php if ($view_feedback_message['type'] !== 'success'): ?>
    <form action='<?= htmlspecialchars(DOMAIN_ROOT . "/register.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' method='POST' style="margin-top:15px;">
      <input type="hidden" name="register_action" value="1" />
      <table class='border-gradient' style='width: 100%;'>
        <tbody>
          <tr>
            <td style='padding: 8px; width: 50%;'>
              <b>Username</b><br />
              <input type='text' name='Username' value="<?= $view_submitted_username ?>" required style="width: 95%; padding: 5px;" />
              <br /><br />
              <b>Email Address</b><br />
              <input type='email' name='Email' value="<?= $view_submitted_email ?>" required style="width: 95%; padding: 5px;" />
              <br /><br />
              <b>Gender</b><br />
              <select name='Gender' style='padding: 5px; width: calc(95% + 10px);'>
                <option value='Ungendered' <?= (isset($_POST['Gender']) && $_POST['Gender'] == 'Ungendered' ? 'selected' : '') ?>>Ungendered</option>
                <option value='Female' <?= (isset($_POST['Gender']) && $_POST['Gender'] == 'Female' ? 'selected' : '') ?>>Female</option>
                <option value='Male' <?= (isset($_POST['Gender']) && $_POST['Gender'] == 'Male' ? 'selected' : '') ?>>Male</option>
              </select>
            </td>
            <td style='padding: 8px; width: 50%;'>
              <b>Password</b><br />
              <input type='password' name='Password' required style="width: 95%; padding: 5px;" />
              <br /><br />
              <b>Confirm Password</b><br />
              <input type='password' name='Password_Confirm' required style="width: 95%; padding: 5px;" />
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <td style='padding: 8px;'>
              <b>Choose An Avatar</b><br />
              <select name='Avatar' onchange='PreviewImage("Avatar", this);' style='padding: 5px; width: calc(95% + 10px);'>
                <?php
                  // This glob might be better handled in controller if possible, or ensure path is safe
                  $Preset_Avatars = glob(__DIR__ . '/../../images/Avatars/Sprites/*.png'); // Adjusted path relative to this view
                  foreach ( $Preset_Avatars as $Avatar_File_Path ) {
                    $Avatar_File_Name = basename($Avatar_File_Path);
                    $Avatar_ID = (int) filter_var($Avatar_File_Name, FILTER_SANITIZE_NUMBER_INT);
                    if ($Avatar_ID > 0) {
                        $is_selected = (isset($_POST['Avatar']) && (int)$_POST['Avatar'] == $Avatar_ID) ? 'selected' : '';
                        echo "<option value='{$Avatar_ID}' {$is_selected}>Avatar #{$Avatar_ID}</option>";
                    }
                  }
                ?>
              </select>
              <br />
              <img src='<?= htmlspecialchars(DOMAIN_SPRITES . '/Avatars/Sprites/1.png', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' id='Avatar_Preview' style="margin-top:5px; max-width:100px; max-height:100px;" />
            </td>
            <td style='padding: 8px;'>
              <b>Choose A Starter</b><br />
              <select name='Starter' onchange='PreviewImage("Starter", this);' style='padding: 5px; width: calc(95% + 10px);'>
                <?php
                  $Possible_Starters = [
                    1 => 'Bulbasaur', 4 => 'Charmander', 7 => 'Squirtle',
                    152 => 'Chikorita', 155 => 'Cyndaquil', 158 => 'Totodile',
                    252 => 'Treecko', 255 => 'Torchic', 258 => 'Mudkip',
                    387 => 'Turtwig', 390 => 'Chimchar', 393 => 'Piplup',
                    495 => 'Snivy', 498 => 'Tepig', 501 => 'Oshawott',
                    650 => 'Chespin', 653 => 'Fennekin', 656 => 'Froakie',
                    722 => 'Rowlet', 725 => 'Litten', 728 => 'Popplio',
                    810 => 'Grookey', 813 => 'Scorbunny', 816 => 'Sobble'
                  ];
                  foreach ( $Possible_Starters as $Starter_ID => $Starter_Name ) {
                    $is_selected = (isset($_POST['Starter']) && (int)$_POST['Starter'] == $Starter_ID) ? 'selected' : '';
                    echo "<option value='{$Starter_ID}' {$is_selected}>" . htmlspecialchars($Starter_Name, ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</option>";
                  }
                ?>
              </select>
              <br />
              <img src='<?= htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Sprites/Normal/001.png', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' id='Starter_Preview' style="margin-top:5px; max-width:96px; max-height:96px;" />
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <td colspan='2' style='padding: 10px; text-align: center;'>
              <input type='submit' name='Register' value='Register' />
            </td>
          </tr>
        </tbody>
      </table>
    </form>
    <?php endif; ?>
  </div>
</div>

<script type='text/javascript'>
  function PreviewImage(Preview_Target, Handler) {
    let Image_Value = Handler.value;
    let Dir_Path = '';
    let default_img = '';

    if (Preview_Target === 'Starter') {
      Image_Value = String(Image_Value).padStart(3, '0'); // Ensure 3 digits for Pokemon
      Dir_Path = '<?= htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Sprites/Normal', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';
      default_img = '<?= htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Sprites/Normal/000.png', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';
    } else if (Preview_Target === 'Avatar') {
      Dir_Path = '<?= htmlspecialchars(DOMAIN_SPRITES . '/Avatars/Sprites', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';
      default_img = '<?= htmlspecialchars(DOMAIN_SPRITES . '/Avatars/Sprites/1.png', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>';
    } else {
      return; // Unknown target
    }

    const Image_Source = `${Dir_Path}/${Image_Value}.png`;
    const Preview_Element = document.getElementById(`${Preview_Target}_Preview`);

    if (Preview_Element) {
      // Check if image exists, fallback to default or current if not
      const imgTest = new Image();
      imgTest.onload = function() { Preview_Element.src = Image_Source; };
      imgTest.onerror = function() { Preview_Element.src = default_img; }; // Fallback to a default if specific sprite not found
      imgTest.src = Image_Source;
    }
  }

  // Initialize previews if form was re-populated
  document.addEventListener('DOMContentLoaded', function() {
    const avatarSelect = document.querySelector('select[name="Avatar"]');
    const starterSelect = document.querySelector('select[name="Starter"]');
    if (avatarSelect && avatarSelect.value) {
      PreviewImage('Avatar', avatarSelect);
    }
    if (starterSelect && starterSelect.value) {
      PreviewImage('Starter', starterSelect);
    }
  });
</script>
