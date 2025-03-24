<?php
/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/includes
 */
class Jackalopes_Server_Activator {

    /**
     * Activate the plugin.
     *
     * Creates necessary database tables and sets up initial plugin options.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;
        
        // Create database tables for game sessions, players, etc.
        $charset_collate = $wpdb->get_charset_collate();
        
        // Game sessions table
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        $sql_sessions = "CREATE TABLE $table_sessions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_key varchar(64) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            max_players int(11) NOT NULL DEFAULT 16,
            current_players int(11) NOT NULL DEFAULT 0,
            settings longtext,
            PRIMARY KEY  (id),
            UNIQUE KEY session_key (session_key)
        ) $charset_collate;";
        
        // Players table
        $table_players = $wpdb->prefix . 'jackalopes_players';
        $sql_players = "CREATE TABLE $table_players (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            user_id bigint(20),
            player_name varchar(64) NOT NULL,
            player_key varchar(64) NOT NULL,
            joined_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_active datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            player_data longtext,
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Game state snapshots table
        $table_snapshots = $wpdb->prefix . 'jackalopes_snapshots';
        $sql_snapshots = "CREATE TABLE $table_snapshots (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id bigint(20) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            snapshot_data longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_sessions);
        dbDelta($sql_players);
        dbDelta($sql_snapshots);
        
        // Set default options
        add_option('jackalopes_server_port', '8080');
        add_option('jackalopes_server_max_connections', '100');
        add_option('jackalopes_server_auto_start', '0');
        add_option('jackalopes_server_log_level', 'info');
        
        // Install Node.js dependencies
        self::install_node_dependencies();
    }
    
    /**
     * Install Node.js dependencies for the WebSocket server
     *
     * @since    1.0.0
     * @access   private
     */
    private static function install_node_dependencies() {
        $plugin_dir = JACKALOPES_SERVER_PLUGIN_DIR;
        
        // Check if package.json exists
        if (!file_exists($plugin_dir . 'package.json')) {
            return;
        }
        
        // Run npm install in the plugin directory
        $command = sprintf(
            'cd %s && npm install',
            escapeshellarg($plugin_dir)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            error_log('Failed to install Node.js dependencies for Jackalopes Server plugin');
        }
    }
} 