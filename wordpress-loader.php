<?php
/**
 * Optional WordPress loader for standalone server operation.
 * 
 * This script attempts to load WordPress core when the server
 * is run outside of the normal WordPress plugin context.
 * 
 * To use this, create a .env file with WordPress path information.
 */

// Don't run if already in WordPress context
if (defined('ABSPATH')) {
    return;
}

// Check for .env file
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }
}

// Get WordPress path from environment
$wpPath = getenv('WORDPRESS_PATH');

if (!$wpPath) {
    echo "WordPress path not specified. Running in standalone mode without WordPress integration.\n";
    return;
}

// Make sure the path exists
if (!file_exists($wpPath . '/wp-load.php')) {
    echo "WordPress installation not found at specified path. Running in standalone mode.\n";
    return;
}

// Load minimal WordPress
define('WP_USE_THEMES', false);
require_once $wpPath . '/wp-load.php';

// Load plugin database classes
require_once __DIR__ . '/includes/class-database.php';

echo "WordPress loaded successfully. Database functions available.\n"; 