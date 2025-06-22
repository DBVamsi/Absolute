<?php // app/views/shop/show_view.php ?>

<div class='panel content'>
    <div class='head'><?= ($view_shop_data ? htmlspecialchars($view_shop_data['Name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'Shop'); ?></div>
    <div class='body'>
        <?php if (!empty($view_error_message)): ?>
            <div style='margin: auto; padding: 10px; color: red; text-align: center;'>
                <?= htmlspecialchars($view_error_message, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
            </div>
        <?php elseif (!$view_shop_data): ?>
            <div style='margin: auto; padding: 10px; text-align: center;'>
                An error occurred while loading the shop data. Please try again.
            </div>
        <?php else: ?>
            <?php /* Navigation for different shops - can be made dynamic if needed */ ?>
            <div class='nav'>
                <div>
                    <a href='<?= htmlspecialchars(DOMAIN_ROOT . "/shop.php?Shop=1", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' style='display: block;'>Pok&eacute;mon Mart (Sample)</a>
                </div>
                <?php /* Add other shop links here if applicable */ ?>
            </div>

            <div class='description' style="padding: 5px; margin-bottom: 10px; border-bottom: 1px solid #ccc;">
                <?= htmlspecialchars($view_shop_data['Description'] ?? 'Welcome to the shop!', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>
            </div>

            <div id='ShopAJAX' style="margin-bottom: 10px; text-align: center;"></div>

            <?php // Display Pokémon for Sale ?>
            <?php if (!empty($view_pokemon_for_sale)): ?>
                <div style='width: 100%; margin-bottom: 20px;'>
                    <h3 style="text-align: center; margin-bottom: 10px;">Pok&eacute;mon For Sale</h3>
                    <div class='flex wrap' style='justify-content: center; gap: 10px;'>
                        <?php foreach ($view_pokemon_for_sale as $pokemon_entry): ?>
                            <?php
                                $shop_details = $pokemon_entry['Shop_Details'];
                                $pokedex_details = $pokemon_entry['Pokedex_Details'];
                                $price_list_parsed = $pokemon_entry['Price_List_Parsed'];
                                $can_afford = $pokemon_entry['Can_Afford'];

                                $purchase_button_html = "";
                                if (($shop_details['Remaining'] ?? 0) < 1) {
                                    $purchase_button_html = "<button class='disabled' disabled>Not In Stock</button>";
                                } elseif ($can_afford) {
                                    $purchase_button_html = "<button onclick='Purchase({ \"ID\": " . (int)$shop_details['ID'] . ", \"Type\": \"Pokemon\" });'>Purchase</button>";
                                } else {
                                    $purchase_button_html = "<button class='disabled' disabled>Can't Afford</button>";
                                }

                                $object_name_escaped = htmlspecialchars($pokedex_details['Display_Name'] ?? 'Unknown Pokemon', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $object_image_escaped = htmlspecialchars($pokedex_details['Sprite'] ?? (DOMAIN_SPRITES . '/Pokemon/Sprites/Normal/000.png'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $stock_id = "Pokemon_" . (int)$shop_details['ID'];
                            ?>
                            <table class='border-gradient' style='flex-basis: 210px; margin: 5px;'>
                                <thead>
                                    <tr><th colspan='2'><?= $object_name_escaped ?></th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan='1' style='width: 96px; text-align: center;'>
                                            <img src='<?= $object_image_escaped ?>' alt="<?= $object_name_escaped ?>" />
                                        </td>
                                        <td colspan='1' style="vertical-align: top;">
                                            <?php foreach ($price_list_parsed as $currency => $amount): ?>
                                                <div style='display: flex; align-items: center; justify-content: flex-start; gap: 5px; margin-bottom: 3px;'>
                                                    <div><img src='<?= htmlspecialchars(DOMAIN_SPRITES . "/Assets/" . $currency . ".png", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' alt="<?= htmlspecialchars($currency, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" /></div>
                                                    <div><?= htmlspecialchars(number_format($amount), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan='2' style="text-align: center;">Stock: <span id="<?= $stock_id ?>"><?= htmlspecialchars($shop_details['Remaining'] ?? 0, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span></td>
                                    </tr>
                                </tbody>
                                <tbody>
                                    <tr><td colspan='2' style="text-align: center;"><?= $purchase_button_html ?></td></tr>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php // Display Items for Sale ?>
            <?php if (!empty($view_items_for_sale)): ?>
                <div style='width: 100%;'>
                    <h3 style="text-align: center; margin-bottom: 10px; margin-top:20px; border-top: 1px solid #ccc; padding-top: 20px;">Items For Sale</h3>
                    <div class='flex wrap' style='justify-content: center; gap: 10px;'>
                        <?php foreach ($view_items_for_sale as $item_entry): ?>
                            <?php
                                $shop_details = $item_entry['Shop_Details'];
                                $item_details = $item_entry['Item_Details'];
                                $price_list_parsed = $item_entry['Price_List_Parsed'];
                                $can_afford = $item_entry['Can_Afford'];

                                $purchase_button_html = "";
                                if (($shop_details['Remaining'] ?? 0) < 1) {
                                    $purchase_button_html = "<button class='disabled' disabled>Not In Stock</button>";
                                } elseif ($can_afford) {
                                    $purchase_button_html = "<button onclick='Purchase({ \"ID\": " . (int)$shop_details['ID'] . ", \"Type\": \"Item\" });'>Purchase</button>";
                                } else {
                                    $purchase_button_html = "<button class='disabled' disabled>Can't Afford</button>";
                                }

                                $object_name_escaped = htmlspecialchars($item_details['Name'] ?? 'Unknown Item', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $object_image_escaped = htmlspecialchars($item_details['Icon'] ?? (DOMAIN_SPRITES . '/Items/default.png'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                                $stock_id = "Item_" . (int)$shop_details['ID'];
                            ?>
                            <table class='border-gradient' style='flex-basis: 210px; margin: 5px;'>
                                <thead>
                                    <tr><th colspan='2'><?= $object_name_escaped ?></th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan='1' style='width: 96px; text-align: center;'>
                                            <img src='<?= $object_image_escaped ?>' alt="<?= $object_name_escaped ?>"/>
                                        </td>
                                        <td colspan='1' style="vertical-align: top;">
                                            <?php foreach ($price_list_parsed as $currency => $amount): ?>
                                                <div style='display: flex; align-items: center; justify-content: flex-start; gap: 5px; margin-bottom: 3px;'>
                                                    <div><img src='<?= htmlspecialchars(DOMAIN_SPRITES . "/Assets/" . $currency . ".png", ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>' alt="<?= htmlspecialchars($currency, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" /></div>
                                                    <div><?= htmlspecialchars(number_format($amount), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </td>
                                    </tr>
                                     <tr>
                                        <td colspan='2' style="text-align: center;">Stock: <span id="<?= $stock_id ?>"><?= htmlspecialchars($shop_details['Remaining'] ?? 0, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span></td>
                                    </tr>
                                    <?php if (!empty($item_details['Description'])): ?>
                                    <tr>
                                        <td colspan='2' style="text-align: center; font-size:0.9em; padding: 4px;">
                                            <?= htmlspecialchars($item_details['Description'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tbody>
                                    <tr><td colspan='2' style="text-align: center;"><?= $purchase_button_html ?></td></tr>
                                </tbody>
                            </table>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($view_pokemon_for_sale) && empty($view_items_for_sale) && empty($view_error_message)): ?>
                <div style='padding: 20px; text-align: center;'>This shop currently has no items or Pok&eacute;mon for sale.</div>
            <?php endif; ?>

        <?php endif; // End of main shop data check ?>
    </div>
</div>

<script type='text/javascript'>
    // Ensure Shop_ID is available for JS if needed, passed from controller or use PHP value directly in AJAX URL
    const currentShopID = <?= (int)$Shop_ID ?>;

    const Purchase = (Object) => {
        return new Promise((resolve, reject) => {
            const req = new XMLHttpRequest();
            // Using currentShopID from PHP scope directly into JS
            req.open('GET', `<?= htmlspecialchars(DOMAIN_ROOT . '/core/ajax/shop/purchase.php', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>?Shop=${currentShopID}&Object_ID=${Object.ID}&Object_Type=${Object.Type}`);
            req.send(null);
            req.onerror = (error) => {
                document.querySelector('#ShopAJAX').innerHTML = "<div class='error'>Network error. Please try again.</div>";
                reject(`Network Error: ${error}`);
            };
            req.onload = () => {
                if (req.status === 200) {
                    document.querySelector('#ShopAJAX').innerHTML = req.responseText;
                    FetchStock(Object); // Update stock count after purchase attempt
                    resolve(req.response);
                } else {
                    document.querySelector('#ShopAJAX').innerHTML = "<div class='error'>Error processing purchase. Please try again.</div>";
                    FetchStock(Object); // Still update stock as it might have changed server-side on error
                    reject(req.statusText);
                }
            };
        });
    }

    const FetchStock = (Object) => {
        return new Promise((resolve, reject) => {
            const stockElement = document.querySelector(`#${Object.Type}_${Object.ID}`);
            if (!stockElement) {
                // console.warn("Stock element not found for:", Object);
                resolve("Stock element not found, but proceeding."); // Don't break if one element is missing
                return;
            }

            const req = new XMLHttpRequest();
            req.open('GET', `<?= htmlspecialchars(DOMAIN_ROOT . '/core/ajax/shop/fetch_stock.php', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>?Object_ID=${Object.ID}&Object_Type=${Object.Type}`);
            req.send(null);
            req.onerror = (error) => {
                stockElement.innerHTML = "Error"; // Update specific stock element
                reject(`Network Error: ${error}`);
            };
            req.onload = () => {
                if (req.status === 200) {
                    stockElement.innerHTML = req.responseText;
                    resolve(req.response);
                } else {
                    stockElement.innerHTML = "Error"; // Update specific stock element
                    reject(req.statusText);
                }
            };
        });
    }
</script>
