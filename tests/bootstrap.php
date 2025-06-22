<?php
// tests/bootstrap.php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Set a base directory variable
// Assumes this bootstrap.php is in /tests/ so dirname(__DIR__) is the project root.
define('TEST_DIR_ROOT', dirname(__DIR__));
define('APP_DIR_ROOT', TEST_DIR_ROOT . '/app');

// A simple autoloader for project classes if no Composer autoloader is used.
spl_autoload_register(function ($class_name) {
    // Define an array of possible base directories for classes
    $class_dirs = [
        APP_DIR_ROOT . '/core/classes/',
        APP_DIR_ROOT . '/staff/classes/',
        APP_DIR_ROOT . '/maps/classes/',
        APP_DIR_ROOT . '/battles/classes/', // Added battle classes
        // Add other directories as needed, e.g., app/battles/classes/moves/
    ];

    // Replace namespace separators with directory separators
    $class_file = str_replace('\\', '/', $class_name) . '.php';

    foreach ($class_dirs as $dir) {
        $file = $dir . $class_file;
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    // Fallback for deeply nested classes like battle moves (if not using namespaces)
    // This is a basic example; a more robust autoloader or Composer would be better for complex structures.
    $file_battles_moves_tmp = APP_DIR_ROOT . '/battles/classes/moves_tmp/' . $class_file;
    if (file_exists($file_battles_moves_tmp)) {
        require_once $file_battles_moves_tmp;
        return;
    }
});

// Include global constants and core functions if not defined elsewhere accessible by tests
// These are needed for many classes to function correctly even if DB is mocked.
if (file_exists(APP_DIR_ROOT . '/core/required/domains.php')) {
    require_once APP_DIR_ROOT . '/core/required/domains.php';
}

// Instantiate Constants if your classes rely on it being a global or accessible.
// For true unit tests, dependencies like this should be injected or constants should be actual PHP constants.
if (file_exists(APP_DIR_ROOT . '/core/classes/constants.php')) {
    require_once APP_DIR_ROOT . '/core/classes/constants.php';
    // $Constants = new Constants(); // Make available globally if needed by legacy code/tests
                                   // For unit tests, avoid global state.
}

// Global functions that might be needed by classes (e.g., FetchLevel in PokemonService)
// This is not ideal for unit testing; such functions should be part of classes or services.
if (file_exists(APP_DIR_ROOT . '/core/functions/formulas.php')) {
    require_once APP_DIR_ROOT . '/core/functions/formulas.php';
}
// HandleError is also global, but for tests, we'd prefer exceptions to be thrown.
// The actual database.php is not included to avoid real DB connections for unit tests.
// We'll need to mock PDO for classes that expect it.

echo "PHPUnit Bootstrap Loaded (TEST_DIR_ROOT: " . TEST_DIR_ROOT . ", APP_DIR_ROOT: " . APP_DIR_ROOT . ").\n";
?>
