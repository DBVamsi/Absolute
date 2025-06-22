<?php // app/views/pokemon_center/main_view.php ?>

<div class='panel content'>
  <div class='head'><?= htmlspecialchars($view_page_title, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
  <div class='body'>
    <div class='nav'>
      <div><a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=roster'>Roster</a></div>
      <div><a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=pc_box'>PC Box</a></div>
      <div><a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=heal'>Heal Pok&eacute;mon</a></div>
      <?php /* Links below will still use AJAX for now, or can be progressively enhanced */ ?>
      <div><a href='javascript:void(0);' onclick="ShowTabLegacy('moves');">Moves</a></div>
      <div><a href='javascript:void(0);' onclick="ShowTabLegacy('inventory');">Inventory</a></div>
      <div><a href='javascript:void(0);' onclick="ShowTabLegacy('nickname');">Nickname</a></div>
      <div><a href='javascript:void(0);' onclick="ShowTabLegacy('release');">Release</a></div>
      <div><a href='javascript:void(0);' onclick="ShowTabLegacy('evolution');">Evolution</a></div>
    </div>

    <?php if (!empty($view_healing_status)): ?>
      <div class='success' style='margin: 5px auto; padding: 5px; max-width: 500px; text-align: center;'>
        <?= htmlspecialchars($view_healing_status, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class='flex wrap' id='Pokemon_Center_Content' style='justify-content: center;'>
      <?php
        // Load content based on action for PHP-driven sections
        switch ($current_action) {
          case 'roster':
            // Include the roster display partial view
            $roster_view_path = __DIR__ . '/_roster_display_view.php';
            if (file_exists($roster_view_path)) {
              require $roster_view_path;
            } else {
              echo "<p>Roster view is currently unavailable.</p>";
            }
            break;

          case 'pc_box':
            $pc_box_view_path = __DIR__ . '/_pc_box_view.php';
            if (file_exists($pc_box_view_path)) {
              require $pc_box_view_path;
            } else {
              echo "<p>PC Box view is currently unavailable.</p>";
            }
            break;

          // For actions still handled by legacy AJAX:
          // The ShowTabLegacy function will target a specific div for AJAX content.
          // We need a container for that.
          default:
            echo "<div id='AJAX_Content_Area' style='display: flex; align-items: center; justify-content: center; padding: 10px; width: 100%;'><div class='loading-element'></div><p style='margin-left: 5px;'>Loading section...</p></div>";
            break;
        }
      ?>
    </div>
  </div>
</div>

<?php // Load necessary JS files - some might be for legacy AJAX tabs ?>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/ajax_functions.js'></script>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/inventory.js'></script>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/nickname.js'></script>
<?php /* roster.js might be partially or fully replaced by PHP view */ ?>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/roster.js'></script>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/moves.js'></script>
<script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/release.js'></script>
<?php /* Add evolution.js if it exists */ ?>
<?php /* <script src='<?= DOMAIN_ROOT; ?>/pages/pokemon_center/js/evolution.js'></script> */ ?>


<script type='text/javascript'>
  // This function is for tabs that are still AJAX-driven
  function ShowTabLegacy(tabName) {
    // Assuming your existing AJAX functions (like those in ajax_functions.js)
    // target a specific area, e.g., '#AJAX_Content_Area' or similar.
    // You might need to adjust this based on how those functions work.
    $('#AJAX_Content_Area').html("<div style='display: flex; align-items: center; justify-content: center; padding: 10px; width: 100%;'><div class='loading-element'></div><p style='margin-left: 5px;'>Loading " + tabName + "...</p></div>");

    // Example calls to existing functions (these would need to exist in your JS files)
    // Ensure these functions populate content within '#AJAX_Content_Area'
    // or a similar consistent target div.
    switch(tabName) {
      case 'moves':
        GetPokemonMoves(); // Assuming this populates #AJAX_Content_Area or similar
        break;
      case 'inventory':
        GetInventory('All'); // Assuming this populates #AJAX_Content_Area or similar
        break;
      case 'nickname':
        GetNicknamePokemon(); // Assuming this populates #AJAX_Content_Area or similar
        break;
      case 'release':
        GetReleasablePokemon(); // Assuming this populates #AJAX_Content_Area or similar
        break;
      case 'evolution':
        // GetEvolutionEligible(); // Example
        $('#AJAX_Content_Area').html("<p>Evolution section (via AJAX) under construction.</p>");
        break;
      case 'pc_box':
         // This is now a PHP-driven link mostly, but if direct JS call is needed for pagination:
        LoadPCBox(1); // Assumes LoadPCBox populates #AJAX_PC_Box_Content
        break;
    }
  }

  $(document).ready(function() {
    <?php
      // If the current action is one that's meant to be loaded via AJAX by default
      // (e.g. if we didn't make 'roster' PHP-driven and wanted JS to load it)
      // or if it's a specific AJAX-driven section like pc_box from a direct link.
      // Remove the automatic ShowTabLegacy('pc_box') call from here
      // as pc_box is now primarily PHP-driven.
      // Specific AJAX calls for pagination within pc_box might be handled by _pc_box_view.php if needed,
      // or pc_box_view.php will generate pagination links that reload the page with new 'pc_page' GET param.
      switch ($current_action) {
          // Example: If 'moves' was still AJAX-first, you might have:
          // case 'moves':
          //  echo "ShowTabLegacy('moves');\n";
          //  break;
      }
    ?>
  });
</script>
