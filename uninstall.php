<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    Jackalopes_Server
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Stop any running server before uninstalling
require_once plugin_dir_path(__FILE__) . 'includes/class-websocket-server.php';
$server = new Jackalopes_Server_WebSocket();
$server->stop();

// Drop plugin database tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}jackalopes_sessions");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}jackalopes_players");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}jackalopes_snapshots");

// Delete plugin options
delete_option('jackalopes_server_port');
delete_option('jackalopes_server_max_connections');
delete_option('jackalopes_server_auto_start');
delete_option('jackalopes_server_log_level');

// Delete plugin files
$files_to_delete = array(
    'server.pid',
    'server.log',
    'plugin.log'
);

$plugin_dir = plugin_dir_path(__FILE__);
foreach ($files_to_delete as $file) {
    $file_path = $plugin_dir . $file;
    if (file_exists($file_path)) {
        unlink($file_path);
    }
} 