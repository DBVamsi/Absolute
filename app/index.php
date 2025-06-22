<?php
    require_once __DIR__ . '/core/required/layout_top.php';

    // Initialize view variables
    $view_is_logged_in = isset($User_Data); // $User_Data is set in session.php if logged in
    $view_username = $view_is_logged_in ? $User_Data['Username'] : null;
    $view_site_stats = ['user_count' => 0, 'pokemon_count' => 0]; // Default values

    // Fetch site statistics
    if (isset($User_Class)) { // User_Class is an instance of User from session.php
        $view_site_stats = $User_Class->FetchSiteStatistics();
    }

    // Any other data preparation for the homepage can go here
    // For example, fetching latest news (if a news service existed)
    // $view_latest_news = $News_Service->FetchLatestNews(3);

    // Path to the view file
    $view_file_path = __DIR__ . '/views/main/index_view.php';

    if (file_exists($view_file_path)) {
        require_once $view_file_path;
    } else {
        // Fallback or error if view file is missing
        echo "<div class='panel content'><div class='head'>Error</div><div class='body' style='padding: 5px;'>Homepage view is currently unavailable.</div></div>";
    }

    require_once __DIR__ . '/core/required/layout_bottom.php';
?>
