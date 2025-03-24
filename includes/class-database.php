<?php
/**
 * The database operations class.
 *
 * Handles all database interactions for the Jackalopes server.
 *
 * @since      1.0.0
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/includes
 */
class Jackalopes_Server_Database {

    /**
     * Create a new game session
     *
     * @since    1.0.0
     * @param    int       $max_players    Maximum number of players allowed in the session
     * @param    string    $settings       JSON string of session settings
     * @return   array                     Array with status and session data or error message
     */
    public static function create_session($max_players = 16, $settings = '') {
        global $wpdb;
        
        // Generate a unique session key
        $session_key = self::generate_session_key();
        
        // Insert the session into the database
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        $result = $wpdb->insert(
            $table_sessions,
            array(
                'session_key' => $session_key,
                'max_players' => intval($max_players),
                'settings' => $settings,
                'status' => 'active',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => 'Failed to create session: ' . $wpdb->last_error
            );
        }
        
        $session_id = $wpdb->insert_id;
        
        return array(
            'success' => true,
            'session' => array(
                'id' => $session_id,
                'session_key' => $session_key,
                'max_players' => $max_players,
                'current_players' => 0,
                'created_at' => current_time('mysql'),
            )
        );
    }
    
    /**
     * Get a game session by ID
     *
     * @since    1.0.0
     * @param    int       $session_id    Session ID
     * @return   object|null              Session object or null if not found
     */
    public static function get_session($session_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_sessions WHERE id = %d",
            $session_id
        ));
    }
    
    /**
     * Get a game session by session key
     *
     * @since    1.0.0
     * @param    string    $session_key    Session key
     * @return   object|null               Session object or null if not found
     */
    public static function get_session_by_key($session_key) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_sessions WHERE session_key = %s",
            $session_key
        ));
    }
    
    /**
     * Get active game sessions
     *
     * @since    1.0.0
     * @return   array    Array of active session objects
     */
    public static function get_active_sessions() {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        
        return $wpdb->get_results(
            "SELECT * FROM $table_sessions WHERE status = 'active' ORDER BY created_at DESC"
        );
    }
    
    /**
     * Close a game session
     *
     * @since    1.0.0
     * @param    int       $session_id    Session ID
     * @return   bool                     True on success, false on failure
     */
    public static function close_session($session_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        
        return $wpdb->update(
            $table_sessions,
            array(
                'status' => 'closed',
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $session_id),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Delete a game session
     *
     * @since    1.0.0
     * @param    int       $session_id    Session ID
     * @return   bool                     True on success, false on failure
     */
    public static function delete_session($session_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
        $table_players = $wpdb->prefix . 'jackalopes_players';
        $table_snapshots = $wpdb->prefix . 'jackalopes_snapshots';
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        // Delete players associated with this session
        $wpdb->delete(
            $table_players,
            array('session_id' => $session_id),
            array('%d')
        );
        
        // Delete snapshots associated with this session
        $wpdb->delete(
            $table_snapshots,
            array('session_id' => $session_id),
            array('%d')
        );
        
        // Delete the session
        $result = $wpdb->delete(
            $table_sessions,
            array('id' => $session_id),
            array('%d')
        );
        
        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return false;
        }
        
        $wpdb->query('COMMIT');
        return true;
    }
    
    /**
     * Add player to a session
     *
     * @since    1.0.0
     * @param    int       $session_id     Session ID
     * @param    string    $player_name    Player name
     * @param    int       $user_id        Optional WordPress user ID
     * @return   array                     Array with status and player data or error message
     */
    public static function add_player($session_id, $player_name, $user_id = null) {
        global $wpdb;
        
        // Check if session exists and is active
        $session = self::get_session($session_id);
        if (!$session) {
            return array(
                'success' => false,
                'message' => 'Session not found'
            );
        }
        
        if ($session->status !== 'active') {
            return array(
                'success' => false,
                'message' => 'Session is not active'
            );
        }
        
        if ($session->current_players >= $session->max_players) {
            return array(
                'success' => false,
                'message' => 'Session is full'
            );
        }
        
        // Generate player key
        $player_key = self::generate_player_key();
        
        // Insert player into database
        $table_players = $wpdb->prefix . 'jackalopes_players';
        $result = $wpdb->insert(
            $table_players,
            array(
                'session_id' => $session_id,
                'user_id' => $user_id,
                'player_name' => $player_name,
                'player_key' => $player_key,
                'joined_at' => current_time('mysql'),
                'last_active' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return array(
                'success' => false,
                'message' => 'Failed to add player: ' . $wpdb->last_error
            );
        }
        
        // Update session player count
        $wpdb->update(
            $wpdb->prefix . 'jackalopes_sessions',
            array('current_players' => $session->current_players + 1),
            array('id' => $session_id),
            array('%d'),
            array('%d')
        );
        
        return array(
            'success' => true,
            'player' => array(
                'id' => $wpdb->insert_id,
                'session_id' => $session_id,
                'player_name' => $player_name,
                'player_key' => $player_key,
            )
        );
    }
    
    /**
     * Update player's last active timestamp
     *
     * @since    1.0.0
     * @param    int       $player_id    Player ID
     * @return   bool                    True on success, false on failure
     */
    public static function update_player_activity($player_id) {
        global $wpdb;
        $table_players = $wpdb->prefix . 'jackalopes_players';
        
        return $wpdb->update(
            $table_players,
            array('last_active' => current_time('mysql')),
            array('id' => $player_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Save a game state snapshot
     *
     * @since    1.0.0
     * @param    int       $session_id      Session ID
     * @param    string    $snapshot_data    JSON string of game state data
     * @return   bool                       True on success, false on failure
     */
    public static function save_snapshot($session_id, $snapshot_data) {
        global $wpdb;
        $table_snapshots = $wpdb->prefix . 'jackalopes_snapshots';
        
        return $wpdb->insert(
            $table_snapshots,
            array(
                'session_id' => $session_id,
                'timestamp' => current_time('mysql'),
                'snapshot_data' => $snapshot_data,
            ),
            array('%d', '%s', '%s')
        ) !== false;
    }
    
    /**
     * Generate a unique session key
     *
     * @since    1.0.0
     * @return   string    Unique session key
     */
    private static function generate_session_key() {
        return substr(md5(uniqid(mt_rand(), true)), 0, 16);
    }
    
    /**
     * Generate a unique player key
     *
     * @since    1.0.0
     * @return   string    Unique player key
     */
    private static function generate_player_key() {
        return substr(md5(uniqid(mt_rand(), true)), 0, 32);
    }
} 