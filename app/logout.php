<?php
    // Ensure session.php is included to handle session start and constants if needed.
    // If session_start() is already handled by an auto-prepend file or similar, this might not be strictly necessary
    // but it's good practice to ensure session handling is active.
    require_once __DIR__ . '/core/required/session.php'; // This will define DOMAIN_ROOT

    // Unset all of the session variables.
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    // Finally, destroy the session.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    // Redirect to the homepage or login page
    header("Location: " . DOMAIN_ROOT . "/index.php");
    exit;
?>
