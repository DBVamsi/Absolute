<?php // app/views/pokemon_center/_roster_display_view.php ?>

<?php if (empty($view_roster_pokemon) || !is_array($view_roster_pokemon) || !count(array_filter($view_roster_pokemon))): ?>
    <div style='padding: 10px; text-align: center; width: 100%;'>
        You have no Pok&eacute;mon in your roster.
    </div>
<?php else: ?>
    <div class='flex wrap' style='font-size: 12px; gap: 10px; justify-content: center; width: 100%; padding: 10px;'>
        <?php foreach ($view_roster_pokemon as $pokemon): ?>
            <div style='flex-basis: 160px; display: flex; flex-direction: column; align-items: center; margin-bottom: 10px;'>
                <?php if ($pokemon): ?>
                    <?php
                        // Prepare data with XSS protection
                        $pokemon_id_escaped = htmlspecialchars($pokemon['ID'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $pokemon_sprite = isset($pokemon['Sprite']) ? htmlspecialchars($pokemon['Sprite'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Sprites/0.png', ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
                            $item_icon_display = "<div class='roster-item-icon' title='{$item_name_escaped}'><img src='{$item_icon_url}' alt='{$item_name_escaped}' /></div>";
                        }

                        $gender_icon_display = '';
                        if (!empty($pokemon['Gender_Icon'])) {
                            $gender_icon_url = htmlspecialchars($pokemon['Gender_Icon'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $gender_escaped = htmlspecialchars($pokemon['Gender'] ?? 'Gender', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $gender_icon_display = "<div class='roster-gender-icon' title='{$gender_escaped}'><img src='{$gender_icon_url}' alt='{$gender_escaped}' /></div>";
                        }

                        $hp_percentage = 0;
                        if (isset($pokemon['Stats'][0]) && $pokemon['Stats'][0] > 0 && isset($pokemon['HP_Current'])) {
                             $hp_percentage = round(((int)$pokemon['HP_Current'] / (int)$pokemon['Stats'][0]) * 100);
                        }
                        $hp_bar_class = 'hp-bar-green';
                        if ($hp_percentage < 25) $hp_bar_class = 'hp-bar-red';
                        elseif ($hp_percentage < 50) $hp_bar_class = 'hp-bar-yellow';

                    ?>
                    <div class='roster-pokemon-card border-gradient'>
                        <div class='roster-pokemon-header'>
                            <?= $gender_icon_display ?>
                            <b class='pokemon-name'><?= $pokemon_display_actual_name ?></b>
                            <?= $item_icon_display ?>
                        </div>
                        <div class='roster-pokemon-sprite-container hover'>
                            <img <?= $popup_class ?> src='<?= $pokemon_sprite ?>' alt="<?= $pokemon_display_name ?>" />
                        </div>
                        <div class='roster-pokemon-info'>
                            Level: <?= $pokemon_level ?><br />
                            HP: <?= htmlspecialchars($pokemon['HP_Current'] ?? $pokemon['Stats'][0], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> / <?= htmlspecialchars($pokemon['Stats'][0] ?? 'N/A', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            <div class='hp-bar-background'>
                                <div class='<?= $hp_bar_class ?>' style='width: <?= $hp_percentage ?>%;'></div>
                            </div>
                            <?php if (!empty($pokemon['Status_Effect'])): ?>
                                Status: <span class='status-<?= strtolower(htmlspecialchars($pokemon['Status_Effect'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>'><?= htmlspecialchars($pokemon['Status_Effect'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class='roster-pokemon-actions'>
                            <?php /* Action buttons - these would still primarily use JS/AJAX for now */ ?>
                            <button onclick="PokemonManager(<?= $pokemon_id_escaped ?>, 'Summary');">Summary</button>
                            <button onclick="ShowTabLegacy('moves', <?= $pokemon_id_escaped ?>);">Moves</button>
                            <?php /* Add more buttons as needed: Nickname, Item, etc. */ ?>
                        </div>
                    </div>
                <?php else: // Empty roster slot ?>
                    <div class='roster-pokemon-card empty-slot border-gradient'>
                        <div class='roster-pokemon-sprite-container'>
                            <img src='<?= htmlspecialchars(DOMAIN_SPRITES . '/Pokemon/Sprites/0.png', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' alt="Empty Slot" />
                        </div>
                        <div class='roster-pokemon-info'>
                            <b>Empty Slot</b>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <style>
      .roster-pokemon-card {
        padding: 8px;
        background-color: #f0f0f0;
        min-height: 220px; /* Adjust as needed */
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        align-items: center;
      }
      .roster-pokemon-card.empty-slot {
        justify-content: center;
      }
      .roster-pokemon-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
        margin-bottom: 5px;
        min-height: 30px; /* For alignment */
      }
      .pokemon-name {
        text-align: center;
        font-size: 1.1em;
        flex-grow: 1;
      }
      .roster-item-icon, .roster-gender-icon {
        width: 24px;
        height: 24px;
      }
      .roster-item-icon img, .roster-gender-icon img {
        max-width: 100%;
        max-height: 100%;
      }
      .roster-pokemon-sprite-container {
        width: 96px; /* Standard sprite size */
        height: 96px; /* Standard sprite size */
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .roster-pokemon-sprite-container img {
        max-width: 100%;
        max-height: 100%;
      }
      .roster-pokemon-info {
        text-align: center;
        font-size: 0.9em;
        margin-bottom: 8px;
      }
      .hp-bar-background {
        background-color: #ddd;
        border-radius: 3px;
        height: 8px;
        margin-top: 3px;
        overflow: hidden;
      }
      .hp-bar-green { background-color: #4CAF50; height: 100%; }
      .hp-bar-yellow { background-color: #FFEB3B; height: 100%; }
      .hp-bar-red { background-color: #F44336; height: 100%; }
      .status-toxic { color: purple; font-weight: bold; }
      .status-burn { color: red; font-weight: bold; }
      /* Add other status styles */
      .roster-pokemon-actions button {
        font-size: 0.8em;
        padding: 3px 5px;
        margin: 0 2px;
      }
    </style>
<?php endif; ?>
