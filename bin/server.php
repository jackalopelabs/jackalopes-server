<?php
/**
 * Standalone script to run the WebSocket server.
 * 
 * This script can be run from the command line.
 * Example: php bin/server.php [port]
 */

// Allow script to be run separately from main WordPress plugin
if (!defined('ABSPATH')) {
    // Load Composer autoloader
    $autoloader = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoloader)) {
        die("Please run 'composer install' first to install dependencies.\n");
    }
    require $autoloader;
    
    // Define plugin constants
    define('JACKALOPES_SERVER_PLUGIN_DIR', dirname(__DIR__) . '/');
    
    // Bootstrap database connection if needed
    if (file_exists(__DIR__ . '/../wordpress-loader.php')) {
        require __DIR__ . '/../wordpress-loader.php';
    }
}

// Set port from command line arguments if provided
$port = 8080; // Default port
if (isset($argv[1]) && is_numeric($argv[1])) {
    $port = (int) $argv[1];
}

// Write PID file
file_put_contents(
    JACKALOPES_SERVER_PLUGIN_DIR . 'server.pid', 
    getmypid()
);

// Trap signals for clean shutdown
pcntl_signal(SIGTERM, 'handleSignal');
pcntl_signal(SIGINT, 'handleSignal');

/**
 * Handle termination signals
 */
function handleSignal($signal) {
    echo "\nReceived signal $signal, shutting down...\n";
    
    // Delete PID file
    @unlink(JACKALOPES_SERVER_PLUGIN_DIR . 'server.pid');
    
    exit(0);
}

// Log startup
echo "Starting Jackalopes WebSocket server on port $port...\n";
echo "Press Ctrl+C to stop the server.\n\n";

// Run server
try {
    \Jackalopes\Server\Server::run($port);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    @unlink(JACKALOPES_SERVER_PLUGIN_DIR . 'server.pid');
    exit(1);
} 