<?php // app/views/auth/login_view.php ?>

<div class="panel content" style="margin: 5px auto; max-width: 500px;">
	<div class='head'>Login</div>
	<div class='body' style='padding: 10px;'>
    <div class='nav'>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/index.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Home</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/login.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Login</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/register.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Register</a></div>
			<div><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/discord.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Discord</a></div>
		</div>

    <div class='description' style="margin-top:10px;">
      Please enter your login credentials below.
    </div>

    <?php if (!empty($view_feedback_message['text'])): ?>
      <div style="margin-top: 10px; padding: 8px; border-radius: 3px; color: white; background-color: <?= ($view_feedback_message['type'] == 'success' ? '#4CAF50' : '#f44336') ?>;">
        <?= htmlspecialchars($view_feedback_message['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form action='<?= htmlspecialchars(DOMAIN_ROOT . "/login.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' method='POST' style="margin-top:15px;">
      <input type="hidden" name="login_action" value="1" />
      <table class='border-gradient' style='width: 100%;'>
        <tbody>
          <tr>
            <td style='padding: 8px;'>
              <b>Username</b><br />
              <input type='text' name='Username' value="<?= $view_submitted_username ?>" required style="width: 95%; padding: 5px;" />
            </td>
          </tr>
          <tr>
            <td style='padding: 8px;'>
              <b>Password</b><br />
              <input type='password' name='Password' required style="width: 95%; padding: 5px;" />
            </td>
          </tr>
        </tbody>
        <tbody>
          <tr>
            <td style='padding: 10px; text-align: center;'>
              <input type='submit' name='Login' value='Login' />
            </td>
          </tr>
        </tbody>
      </table>
    </form>
  </div>
</div>
