<?php
    require_once __DIR__ . '/core/required/layout_top.php';

    // Determine Shop ID
    $Shop_ID = isset($_GET['Shop']) ? (int)$_GET['Shop'] : 1;
    if ($Shop_ID < 1) {
        $Shop_ID = 1; // Default to a valid shop ID if input is invalid
    }

    // Initialize view variables
    $view_shop_data = null;
    $view_pokemon_for_sale = [];
    $view_items_for_sale = [];
    $view_error_message = null;

    // Fetch Shop Data
    if ($Shop_Class) { // Ensure Shop_Class (ShopService) is available
        $view_shop_data = $Shop_Class->FetchShopData($Shop_ID);
    } else {
        $view_error_message = "Shop service is currently unavailable.";
    }

    if (!$view_error_message && !$view_shop_data) {
        $view_error_message = "The requested shop (ID: {$Shop_ID}) could not be found.";
    }

    // Fetch Pokémon and Items for sale if shop data was found
    if (!$view_error_message && $view_shop_data) {
        // Fetch Pokémon for Sale
        if ($Pokemon_Service) {
            $shop_pokemon_raw = $Shop_Class->FetchShopPokemon($Shop_ID);
            if ($shop_pokemon_raw) {
                foreach ($shop_pokemon_raw as $shop_pokemon) {
                    if (empty($shop_pokemon['Prices'])) continue;

                    $pokedex_data = $Pokemon_Service->GetPokedexData($shop_pokemon['Pokedex_ID'], $shop_pokemon['Alt_ID'], $shop_pokemon['Type']);
                    if ($pokedex_data) {
                        $can_afford = true;
                        $price_list = $Shop_Class->FetchPriceList($shop_pokemon['Prices']);
                        foreach ($price_list[0] as $currency => $amount) {
                            if (($User_Data[$currency] ?? 0) < $amount) {
                                $can_afford = false;
                                break;
                            }
                        }
                        $view_pokemon_for_sale[] = [
                            'Shop_Details' => $shop_pokemon, // Contains ID, Prices, Remaining, Type (Normal/Shiny for shop)
                            'Pokedex_Details' => $pokedex_data, // Contains Display_Name, Sprite, etc.
                            'Price_List_Parsed' => $price_list[0],
                            'Can_Afford' => $can_afford,
                        ];
                    }
                }
            }
        } else {
            // Potentially set a notice if Pokemon_Service is unavailable
        }

        // Fetch Items for Sale
        if ($Item_Class) { // Item_Class is an instance of Item (ItemService)
            $shop_items_raw = $Shop_Class->FetchShopItems($Shop_ID);
            if ($shop_items_raw) {
                foreach ($shop_items_raw as $shop_item) {
                    if (empty($shop_item['Prices'])) continue;

                    $item_data = $Item_Class->FetchItemData($shop_item['Item_ID']);
                    if ($item_data) {
                        $can_afford = true;
                        $price_list = $Shop_Class->FetchPriceList($shop_item['Prices']);
                        foreach ($price_list[0] as $currency => $amount) {
                            if (($User_Data[$currency] ?? 0) < $amount) {
                                $can_afford = false;
                                break;
                            }
                        }
                        $view_items_for_sale[] = [
                            'Shop_Details' => $shop_item, // Contains ID, Prices, Remaining
                            'Item_Details' => $item_data, // Contains Name, Icon, Description
                            'Price_List_Parsed' => $price_list[0],
                            'Can_Afford' => $can_afford,
                        ];
                    }
                }
            }
        } else {
            // Potentially set a notice if Item_Class is unavailable
        }
    }

    // Path to the view file
    $view_file_path = __DIR__ . '/views/shop/show_view.php';

    if (file_exists($view_file_path)) {
        require_once $view_file_path;
    } else {
        // Fallback or error if view file is missing
        echo "<div class='panel content'><div class='head'>Error</div><div class='body' style='padding: 5px;'>Shop view is currently unavailable.</div></div>";
    }

    require_once __DIR__ . '/core/required/layout_bottom.php';
?>
