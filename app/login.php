<?php
    require_once __DIR__ . '/core/required/layout_top.php';

    // Redirect if already logged in
    if (isset($_SESSION['EvoChroniclesRPG']['User_Data'])) { // Check User_Data specifically for login status
        header("Location: " . DOMAIN_ROOT . "/index.php");
        exit;
    }

    // Initialize view variables
    $view_feedback_message = ['type' => '', 'text' => ''];
    $view_submitted_username = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
        $username_input = $_POST['Username'] ?? '';
        $password_input = $_POST['Password'] ?? ''; // Raw password

        // For re-populating form (username only)
        $view_submitted_username = htmlspecialchars(Purify($username_input), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (empty($username_input) || empty($password_input)) {
            $view_feedback_message = ['type' => 'error', 'text' => 'Username and password are required.'];
        } else {
            $sanitized_username = Purify($username_input); // Purify for lookup, though AuthenticateUser might re-sanitize if needed

            if (isset($User_Class)) {
                $auth_result = $User_Class->AuthenticateUser($sanitized_username, $password_input);

                if ($auth_result['success']) {
                    // Set up the session
                    $_SESSION['EvoChroniclesRPG']['Logged_In_As'] = $auth_result['user_id'];
                    $_SESSION['EvoChroniclesRPG']['Playtime_Start'] = time(); // Initialize playtime start

                    session_regenerate_id(true); // Regenerate session ID to prevent session fixation

                    // Update Last_Login timestamp
                    $User_Class->UpdateLastLogin($auth_result['user_id']);

                    // Fetch full user data for the session (already have user_id and username from auth_result)
                    // It's good to store comprehensive user data in session after login.
                    $User_Data = $User_Class->FetchUserData($auth_result['user_id']);
                    $_SESSION['EvoChroniclesRPG']['User_Data'] = $User_Data;

                    // Redirect to homepage
                    header("Location: " . DOMAIN_ROOT . "/index.php");
                    exit;
                } else {
                    $view_feedback_message = ['type' => 'error', 'text' => $auth_result['message']];
                }
            } else {
                $view_feedback_message = ['type' => 'error', 'text' => 'Authentication service is currently unavailable.'];
            }
        }
    }

    // Path to the view file
    $view_file_path = __DIR__ . '/views/auth/login_view.php';

    if (file_exists($view_file_path)) {
        require_once $view_file_path;
    } else {
        echo "<div class='panel content'><div class='head'>Error</div><div class='body' style='padding: 5px;'>Login view is currently unavailable.</div></div>";
    }

    require_once __DIR__ . '/core/required/layout_bottom.php';
?>
