<?php // app/views/pokemon_center/_pc_box_view.php ?>

<div style="width: 100%; padding: 10px;">
    <?php if (!empty($view_boxed_pokemon_data['error'])): ?>
        <div class='error' style='margin-bottom: 10px; padding: 10px; text-align: center;'>
            <?= htmlspecialchars($view_boxed_pokemon_data['error'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (empty($view_boxed_pokemon_data['pokemon']) && empty($view_boxed_pokemon_data['error'])): ?>
        <div style='padding: 20px; text-align: center; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;'>
            Your PC Box is currently empty.
        </div>
    <?php elseif (!empty($view_boxed_pokemon_data['pokemon'])): ?>
        <div class='flex wrap' style='font-size: 11px; gap: 8px; justify-content: center;'>
            <?php foreach ($view_boxed_pokemon_data['pokemon'] as $pokemon): ?>
                <div class='pc-pokemon-card border-gradient'>
                    <?php
                        $pokemon_id_escaped = htmlspecialchars($pokemon['ID'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $pokemon_sprite = isset($pokemon['Icon']) ? htmlspecialchars($pokemon['Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Icons/Normal/0.png', ENT_QUOTES | ENT_HTML5, 'UTF-8'); // Use Icon for box view
                        $pokemon_display_name = htmlspecialchars($pokemon['Display_Name'] ?? 'Unknown', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $pokemon_level = htmlspecialchars($pokemon['Level'] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $pokemon_nickname_raw = $pokemon['Nickname'] ?? '';
                        $pokemon_display_actual_name = $pokemon_nickname_raw ? htmlspecialchars($pokemon_nickname_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8') : $pokemon_display_name;

                        $pokemon_ajax_url = htmlspecialchars(DOMAIN_ROOT . "/core/ajax/pokemon.php?id=" . $pokemon_id_escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $popup_class = $pokemon_id_escaped ? "class='popup' data-src='{$pokemon_ajax_url}'" : "";

                        $item_icon_display = '';
                        if (!empty($pokemon['Item_ID']) && !empty($pokemon['Item_Icon'])) {
                            $item_icon_url = htmlspecialchars($pokemon['Item_Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $item_name_escaped = htmlspecialchars($pokemon['Item'] ?? 'Item', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $item_icon_display = "<div class='pc-item-icon' title='{$item_name_escaped}'><img src='{$item_icon_url}' alt='{$item_name_escaped}' /></div>";
                        }

                        $gender_icon_display = '';
                        if (!empty($pokemon['Gender_Icon'])) {
                            $gender_icon_url = htmlspecialchars($pokemon['Gender_Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $gender_escaped = htmlspecialchars($pokemon['Gender'] ?? 'Gender', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $gender_icon_display = "<div class='pc-gender-icon' title='{$gender_escaped}'><img src='{$gender_icon_url}' alt='{$gender_escaped}' /></div>";
                        }
                    ?>
                    <div class='pc-pokemon-header'>
                        <?= $gender_icon_display ?>
                        <b class='pokemon-name-pc'><?= $pokemon_display_actual_name ?></b>
                        <?= $item_icon_display ?>
                    </div>
                    <div class='pc-pokemon-sprite-container hover'>
                        <img <?= $popup_class ?> src='<?= $pokemon_sprite ?>' alt="<?= $pokemon_display_name ?>" />
                    </div>
                    <div class='pc-pokemon-info'>
                        Level: <?= $pokemon_level ?>
                    </div>
                    <div class='pc-pokemon-actions'>
                        <button onclick="MoveToRoster(<?= $pokemon_id_escaped ?>);">To Roster</button>
                        <?php /* Add more buttons as needed: View Summary (popup), Release, etc. */ ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
            // Pagination
            $total_pages = ceil($view_boxed_pokemon_data['total_pokemon'] / $view_boxed_pokemon_data['items_per_page']);
            $current_page = $view_boxed_pokemon_data['current_page'];
        ?>
        <?php if ($total_pages > 1): ?>
            <div class='pagination' style='margin-top: 15px; text-align: center;'>
                <?php if ($current_page > 1): ?>
                    <a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=pc_box&pc_page=<?= $current_page - 1 ?>'>&laquo; Previous</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class='current-page'><?= $i ?></span>
                    <?php else: ?>
                        <a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=pc_box&pc_page=<?= $i ?>'><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages): ?>
                    <a href='<?= DOMAIN_ROOT ?>/pokemon_center.php?action=pc_box&pc_page=<?= $current_page + 1 ?>'>Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<style>
  .pc-pokemon-card {
    padding: 5px;
    background-color: #f8f8f8;
    width: 100px; /* Adjust for smaller cards in PC box */
    min-height: 150px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9em;
  }
  .pc-pokemon-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    margin-bottom: 3px;
    min-height: 20px; /* For alignment */
  }
  .pokemon-name-pc {
    text-align: center;
    font-size: 1em; /* Slightly smaller */
    flex-grow: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .pc-item-icon, .pc-gender-icon {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
  }
  .pc-item-icon img, .pc-gender-icon img {
    max-width: 100%;
    max-height: 100%;
  }
  .pc-pokemon-sprite-container {
    width: 64px; /* Smaller icon for box view */
    height: 64px;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pc-pokemon-sprite-container img {
    max-width: 100%;
    max-height: 100%;
  }
  .pc-pokemon-info {
    text-align: center;
    font-size: 0.85em;
    margin-bottom: 5px;
  }
  .pc-pokemon-actions button {
    font-size: 0.75em;
    padding: 2px 4px;
    margin: 1px;
  }
  .pagination a, .pagination span.current-page {
    padding: 5px 8px;
    margin: 0 2px;
    border: 1px solid #ddd;
    text-decoration: none;
    color: #337ab7;
    border-radius: 3px;
  }
  .pagination span.current-page {
    background-color: #337ab7;
    color: white;
    border-color: #337ab7;
  }
  .pagination a:hover {
    background-color: #eee;
  }
</style>

<script type='text/javascript'>
  // JavaScript function to handle moving Pokemon to roster (example)
  // This would typically make an AJAX call
  function MoveToRoster(pokemonId) {
    // This would call an AJAX handler, e.g., using Fetch_AJAX or a custom function
    // For now, it's a placeholder action.
    // The AJAX handler would call PokemonService->MovePokemon(pokemonId, userId, desiredSlot)
    // and then likely reload the pc_box and roster views or parts thereof.
    alert('Attempting to move Pokémon ID: ' + pokemonId + ' to roster. AJAX call needed here.');
    // Example: Fetch_AJAX('pokemon_center_actions.php', { action: 'move_to_roster', pokemon_id: pokemonId }, function(response) { ... });
    // After AJAX success, you might want to reload the current page or specific sections:
    // window.location.reload();
    // Or, more selectively update parts of the page.
  }
</script>
