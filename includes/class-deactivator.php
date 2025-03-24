<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/includes
 */
class Jackalopes_Server_Deactivator {

    /**
     * Deactivate the plugin.
     *
     * Stop any running WebSocket servers and clean up temporary data.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Stop any running WebSocket server
        $server_pid_file = JACKALOPES_SERVER_PLUGIN_DIR . 'server.pid';
        if (file_exists($server_pid_file)) {
            $pid = file_get_contents($server_pid_file);
            if ($pid) {
                // Try to gracefully stop the server
                if (function_exists('posix_kill')) {
                    posix_kill((int) $pid, 15); // SIGTERM
                }
                
                // Clean up the PID file
                unlink($server_pid_file);
            }
        }
        
        // Close all active sessions
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        $wpdb->update(
            $table_sessions,
            ['status' => 'closed'],
            ['status' => 'active']
        );
    }
} 