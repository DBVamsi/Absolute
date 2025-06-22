<?php
    require_once __DIR__ . '/core/required/layout_top.php';

    // Initialize view variables
    $view_target_clan_id = null;
    $view_clan_data = null;
    $view_members_list = [];
    $view_user_clan_role = null; // Logged-in user's role in the currently viewed clan
    $view_can_create_clan = false;
    $view_creation_cost = $Constants->Clan['Creation_Cost'] ?? 50000; // Default if not set for some reason
    $view_feedback_message = ['type' => '', 'text' => '']; // For success/error messages

    // Handle Clan Creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_clan_action'])) {
        if ($User_Data['Clan'] == 0) {
            $clan_name_input = $_POST['clan_name'] ?? '';
            // Basic sanitization for clan name, specific validation should be in Clan_Class->CreateClan
            $sanitized_clan_name = Purify(trim($clan_name_input)); // Using existing Purify

            if (empty($sanitized_clan_name)) {
                $view_feedback_message = ['type' => 'error', 'text' => 'Clan name cannot be empty.'];
            } elseif (mb_strlen($sanitized_clan_name) > 30) { // Example validation
                $view_feedback_message = ['type' => 'error', 'text' => 'Clan name is too long (max 30 characters).'];
            } elseif (($User_Data['Money'] ?? 0) < $view_creation_cost) {
                $view_feedback_message = ['type' => 'error', 'text' => 'You do not have enough money to create a clan.'];
            } else {
                // Conceptual call to Clan_Class method (assumed to handle DB operations, name check, deductions)
                $creation_result = $Clan_Class->CreateClan($User_Data['ID'], $sanitized_clan_name, $view_creation_cost);

                if ($creation_result['success']) {
                    $view_feedback_message = ['type' => 'success', 'text' => $creation_result['message']];
                    // Update session data to reflect new clan membership immediately for the current page load
                    $_SESSION['EvoChroniclesRPG']['User_Data']['Clan'] = $creation_result['clan_id'];
                    $_SESSION['EvoChroniclesRPG']['User_Data']['Clan_Rank'] = 'Administrator'; // Or whatever rank CreateClan assigns
                    $User_Data = $_SESSION['EvoChroniclesRPG']['User_Data']; // Refresh $User_Data for current script execution
                    $view_target_clan_id = $creation_result['clan_id']; // Set target to newly created clan
                } else {
                    $view_feedback_message = ['type' => 'error', 'text' => $creation_result['message']];
                }
            }
        } else {
            $view_feedback_message = ['type' => 'error', 'text' => 'You are already in a clan.'];
        }
    }

    // Determine Target Clan ID if not set by creation
    if ($view_target_clan_id === null) { // Check if not already set by successful creation
        if (isset($_GET['clan_id'])) {
            $clan_id_input = filter_var($_GET['clan_id'], FILTER_SANITIZE_NUMBER_INT);
            $view_target_clan_id = (int)$clan_id_input;
            if ($view_target_clan_id <= 0) $view_target_clan_id = null; // Invalid ID from GET
        } elseif ($User_Data['Clan'] > 0) {
            $view_target_clan_id = $User_Data['Clan'];
        }
    }

    // Fetch Clan Data if a target ID is determined
    if ($view_target_clan_id !== null && $view_target_clan_id > 0) {
        if ($Clan_Class) {
            $view_clan_data = $Clan_Class->FetchClanData($view_target_clan_id);
        } else {
             $view_feedback_message = ['type' => 'error', 'text' => 'Clan service is unavailable.'];
        }

        if ($view_clan_data) {
            // Fetch members
            $member_records = $Clan_Class->FetchMembers($view_clan_data['ID']); // Assuming this returns array of basic member info or just IDs
            if ($member_records) {
                foreach ($member_records as $member_record) {
                    // Assuming FetchMembers returns at least an 'ID' field for each member
                    $member_user_data = $User_Class->FetchUserData($member_record['User_ID'] ?? $member_record['ID']); // Adjust key based on what FetchMembers returns
                    if ($member_user_data) {
                        $view_members_list[] = $member_user_data;
                    }
                }
            }

            // Determine logged-in user's role in this specific clan
            if ($User_Data['Clan'] == $view_clan_data['ID']) {
                $view_user_clan_role = $User_Data['Clan_Rank'];
            }
        } elseif (empty($view_feedback_message['text'])) { // Don't overwrite creation success/error
             $view_feedback_message = ['type' => 'error', 'text' => "The requested clan (ID: " . htmlspecialchars($view_target_clan_id, ENT_QUOTES) . ") was not found."];
        }
    } else {
        // Not viewing a specific clan, and not in one (or just created one and page hasn't fully reloaded with new GET param)
        if ($User_Data['Clan'] == 0 && empty($view_feedback_message['text'])) {
            $view_can_create_clan = true;
        }
    }

    // Path to the view file
    $view_file_path = __DIR__ . '/views/clan/show_view.php';

    if (file_exists($view_file_path)) {
        require_once $view_file_path;
    } else {
        echo "<div class='panel content'><div class='head'>Error</div><div class='body' style='padding: 5px;'>Clan view is currently unavailable.</div></div>";
    }

    require_once __DIR__ . '/core/required/layout_bottom.php';
?>
