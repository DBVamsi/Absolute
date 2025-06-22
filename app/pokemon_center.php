<?php
  require_once __DIR__ . '/core/required/layout_top.php';

  // Available actions in the Pokemon Center
  $available_actions = ['roster', 'pc_box', 'heal', 'moves', 'inventory', 'nickname', 'release', 'evolution']; // Added evolution
  $current_action = $_GET['action'] ?? 'roster'; // Default action

  // Sanitize action
  if (!in_array($current_action, $available_actions)) {
    $current_action = 'roster'; // Default to roster if action is invalid
  }

  // Initialize view variables
  $view_roster_pokemon = [];
  $view_healing_status = null;
  $view_page_title = "Pok&eacute;mon Center"; // Default title
  $view_boxed_pokemon_data = [
      'pokemon' => [],
      'total_pokemon' => 0,
      'current_page' => 1,
      'items_per_page' => 30,
      'error' => null,
  ];

  // Perform actions and fetch data based on the current action
  switch ($current_action) {
    case 'heal':
      if ($Pokemon_Service) { // Ensure service is available
        $healing_result = $Pokemon_Service->HealRosterPokemon($_SESSION['EvoChroniclesRPG']['User_Data']['ID']);
        $view_healing_status = $healing_result['Message'];
      } else {
        $view_healing_status = "Healing service is currently unavailable.";
      }
      // After healing, typically show the roster
      $current_action = 'roster'; // Fallthrough to roster display after setting status
      // Intentional fallthrough

    case 'roster':
      $view_page_title = "Your Roster - Pok&eacute;mon Center";
      $roster_slots_raw = $User_Class->FetchRoster($_SESSION['EvoChroniclesRPG']['User_Data']['ID']);
      if ($roster_slots_raw) {
        foreach ($roster_slots_raw as $roster_entry) {
          if (isset($roster_entry['ID'])) {
            $pokemon_full_data = $Pokemon_Service->GetPokemonData($roster_entry['ID']);
            if ($pokemon_full_data) {
              $view_roster_pokemon[] = $pokemon_full_data;
            } else {
              $view_roster_pokemon[] = null; // Placeholder if data fetch fails
            }
          } else {
            $view_roster_pokemon[] = null; // Empty slot
          }
        }
      }
      // Fill remaining roster slots if less than 6
      for ($i = count($view_roster_pokemon); $i < 6; $i++) {
          $view_roster_pokemon[] = null;
      }
      break;

    // Placeholder for other actions - they will primarily be handled by AJAX for now
    // or can be built out similarly to 'roster' if PHP-driven views are desired.
    case 'pc_box':
      $view_page_title = "PC Box - Pok&eacute;mon Center";
      $pc_page = isset($_GET['pc_page']) ? (int)$_GET['pc_page'] : 1;
      if ($pc_page < 1) {
        $pc_page = 1;
      }
      if ($Pokemon_Service) {
        $view_boxed_pokemon_data = $Pokemon_Service->FetchBoxPokemon($_SESSION['EvoChroniclesRPG']['User_Data']['ID'], $pc_page, 30);
      } else {
        $view_boxed_pokemon_data['error'] = "PC Box service is currently unavailable.";
      }
      break;
    case 'moves':
      $view_page_title = "Manage Moves - Pok&eacute;mon Center";
      break;
    case 'inventory':
      $view_page_title = "Your Inventory - Pok&eacute;mon Center";
      break;
    case 'nickname':
      $view_page_title = "Nickname Rater - Pok&eacute;mon Center";
      break;
    case 'release':
      $view_page_title = "Release Pok&eacute;mon - Pok&eacute;mon Center";
      break;
    case 'evolution':
      $view_page_title = "Evolution Center - Pok&eacute;mon Center";
      break;
  }

  // Path to the main view file
  $view_file_path = __DIR__ . '/views/pokemon_center/main_view.php';

  if (file_exists($view_file_path)) {
    require_once $view_file_path;
  } else {
    echo "<div class='panel content'><div class='head'>Error</div><div class='body' style='padding: 5px;'>Pok&eacute;mon Center view is currently unavailable.</div></div>";
  }

  require_once __DIR__ . '/core/required/layout_bottom.php';
?>
