<?php
/**
 * PHPUnit Bootstrap File
 * 
 * This file is executed before any tests are run.
 * It sets up the testing environment and loads the PAL Autoloader.
 */

// Ensure error reporting is enabled
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define test constants
define('PAL_TEST_DIR', __DIR__);
define('PAL_ROOT_DIR', dirname(__DIR__));
define('PAL_SRC_DIR', PAL_ROOT_DIR . '/src');
define('PAL_FIXTURES_DIR', PAL_TEST_DIR . '/fixtures');

// Load the PAL Autoloader
require_once PAL_SRC_DIR . '/pal/Autoloader.php';

// Register autoloader for test classes
spl_autoload_register(function ($class) {
    if (strpos($class, 'PAL\\Tests\\') === 0) {
        $file = PAL_TEST_DIR . '/' . str_replace(['PAL\\Tests\\', '\\'], ['', '/'], $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

// Create necessary test directories
$dirs = [
    PAL_TEST_DIR . '/results',
    PAL_TEST_DIR . '/temp',
    PAL_FIXTURES_DIR,
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
