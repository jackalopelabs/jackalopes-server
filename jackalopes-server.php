<?php
/**
 * Plugin Name: Jackalopes Server
 * Plugin URI: https://example.com/jackalopes-server
 * Description: Multiplayer server for Jackalopes FPS game
 * Version: 1.0.0
 * Author: Mason Lawlor
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: jackalopes-server
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('JACKALOPES_SERVER_VERSION', '1.0.0');
define('JACKALOPES_SERVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JACKALOPES_SERVER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_jackalopes_server() {
    require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-activator.php';
    Jackalopes_Server_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_jackalopes_server() {
    require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-deactivator.php';
    Jackalopes_Server_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_jackalopes_server');
register_deactivation_hook(__FILE__, 'deactivate_jackalopes_server');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once JACKALOPES_SERVER_PLUGIN_DIR . 'includes/class-jackalopes-server.php';

/**
 * Begins execution of the plugin.
 */
function run_jackalopes_server() {
    $plugin = new Jackalopes_Server();
    $plugin->run();
}

run_jackalopes_server(); 