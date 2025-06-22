<?php // app/views/main/index_view.php ?>

<div class='panel content'>
  <div class='head'>Server Status</div>
  <div class='body'>
    <div style='padding: 5px;'>
      Welcome to Pok&eacute;mon Absolute! We're a fan-made Pok&eacute;mon MMORPG, currently in development.
      <br /><br />
      Feel free to <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/register.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>create an account</a>, or <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/login.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>log in</a> to an existing account.
    </div>
  </div>
</div>

<?php if ($view_is_logged_in && $view_username): ?>
  <div class='panel content'>
    <div class='head'>Welcome Back, <?= htmlspecialchars($view_username, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>!</div>
    <div class='body' style='padding: 5px;'>
      What would you like to do today?
      <ul>
        <li><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/map.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>Explore the World</a></li>
        <li><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/pokemon_center.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>Visit the Pok&eacute;mon Center</a></li>
        <li><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/shop.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>Browse the Shop</a></li>
        <li><a href='<?= htmlspecialchars(DOMAIN_ROOT . "/clan.php", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>'>View Your Clan</a></li>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class='panel content'>
  <div class='head'>Statistics</div>
  <div class='body' style='padding: 5px;'>
    <div class='flex' style='justify-content: center;'>
      <div style='flex-basis: 50%; text-align: center;'>
        <div style='font-size: 18px;'><?= htmlspecialchars(number_format($view_site_stats['user_count']), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
        <div><b>Registered Users</b></div>
      </div>
      <div style='flex-basis: 50%; text-align: center;'>
        <div style='font-size: 18px;'><?= htmlspecialchars(number_format($view_site_stats['pokemon_count']), ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></div>
        <div><b>Total Pok&eacute;mon</b></div>
      </div>
    </div>
  </div>
</div>

<div class='panel content'>
  <div class='head'>Absolute News</div>
  <div class='body' style='padding: 5px;'>
    To be implemented. Check back later for updates!
    <?php /* Placeholder for where news items would be displayed if $view_latest_news was populated */ ?>
  </div>
</div>

<?php
  // Example of how you might display news if it were fetched:
  // if (!empty($view_latest_news)) {
  //   echo "<div class='panel content'><div class='head'>Latest News</div><div class='body' style='padding:5px;'>";
  //   foreach ($view_latest_news as $news_item) {
  //     echo "<div class='news-item' style='margin-bottom:10px; padding-bottom:5px; border-bottom:1px solid #eee;'>";
  //     echo "<h4>" . htmlspecialchars($news_item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</h4>";
  //     echo "<p style='font-size:0.9em;'>" . nl2br(htmlspecialchars($news_item['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "</p>";
  //     echo "<small>Posted on: " . htmlspecialchars(date('M j, Y', $news_item['timestamp']), ENT_QUOTES | ENT_HTML5, 'UTF-8') . "</small>";
  //     echo "</div>";
  //   }
  //   echo "</div></div>";
  // }
?>
