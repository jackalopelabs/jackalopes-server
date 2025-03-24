<?php
/**
 * The REST API functionality of the plugin.
 *
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/public
 */
class Jackalopes_Server_REST_API {

    /**
     * The namespace for the REST API.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $namespace    The namespace for the REST API.
     */
    private $namespace = 'jackalopes/v1';

    /**
     * Register the REST API routes.
     *
     * @since    1.0.0
     */
    public function register_routes() {
        // Server status endpoint
        register_rest_route($this->namespace, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_server_status'),
            'permission_callback' => '__return_true',
        ));
        
        // Active sessions endpoint
        register_rest_route($this->namespace, '/sessions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_sessions'),
            'permission_callback' => '__return_true',
        ));
        
        // Create session endpoint
        register_rest_route($this->namespace, '/sessions', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_session'),
            'permission_callback' => array($this, 'check_session_create_permission'),
            'args' => array(
                'max_players' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 16,
                    'minimum' => 2,
                    'maximum' => 100,
                ),
                'settings' => array(
                    'required' => false,
                    'type' => 'string',
                ),
            ),
        ));
        
        // Join session endpoint
        register_rest_route($this->namespace, '/sessions/(?P<session_key>[a-zA-Z0-9]+)/join', array(
            'methods' => 'POST',
            'callback' => array($this, 'join_session'),
            'permission_callback' => '__return_true',
            'args' => array(
                'player_name' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
        ));
    }
    
    /**
     * Check if user has permission to create a session.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full data about the request.
     * @return   bool                           True if user has permission, false otherwise.
     */
    public function check_session_create_permission($request) {
        // Allow anyone to create a session for now
        // In a production environment, you might want to restrict this
        return true;
    }
    
    /**
     * Get server status.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full data about the request.
     * @return   WP_REST_Response                Response object.
     */
    public function get_server_status($request) {
        $websocket_server = new Jackalopes_Server_WebSocket();
        $status = $websocket_server->get_status();
        
        return rest_ensure_response($status);
    }
    
    /**
     * Get active sessions.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full data about the request.
     * @return   WP_REST_Response                Response object.
     */
    public function get_sessions($request) {
        $active_sessions = Jackalopes_Server_Database::get_active_sessions();
        
        $sessions = array();
        foreach ($active_sessions as $session) {
            $sessions[] = array(
                'id' => $session->id,
                'session_key' => $session->session_key,
                'created_at' => $session->created_at,
                'current_players' => $session->current_players,
                'max_players' => $session->max_players,
            );
        }
        
        return rest_ensure_response($sessions);
    }
    
    /**
     * Create a session.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full data about the request.
     * @return   WP_REST_Response                Response object.
     */
    public function create_session($request) {
        $max_players = $request->get_param('max_players');
        $settings = $request->get_param('settings');
        
        // Validate JSON if settings are provided
        if (!empty($settings)) {
            json_decode($settings);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error(
                    'invalid_json',
                    'Invalid JSON in settings field',
                    array('status' => 400)
                );
            }
        }
        
        $result = Jackalopes_Server_Database::create_session($max_players, $settings);
        
        if (!$result['success']) {
            return new WP_Error(
                'session_create_failed',
                $result['message'],
                array('status' => 500)
            );
        }
        
        return rest_ensure_response($result['session']);
    }
    
    /**
     * Join a session.
     *
     * @since    1.0.0
     * @param    WP_REST_Request    $request    Full data about the request.
     * @return   WP_REST_Response                Response object.
     */
    public function join_session($request) {
        $session_key = $request->get_param('session_key');
        $player_name = $request->get_param('player_name');
        
        // Get user ID if logged in
        $user_id = is_user_logged_in() ? get_current_user_id() : null;
        
        // Get session by key
        $session = Jackalopes_Server_Database::get_session_by_key($session_key);
        
        if (!$session) {
            return new WP_Error(
                'session_not_found',
                'Session not found or not active',
                array('status' => 404)
            );
        }
        
        $result = Jackalopes_Server_Database::add_player($session->id, $player_name, $user_id);
        
        if (!$result['success']) {
            return new WP_Error(
                'join_failed',
                $result['message'],
                array('status' => 400)
            );
        }
        
        $response = array(
            'session' => array(
                'id' => $session->id,
                'key' => $session->session_key,
            ),
            'player' => $result['player'],
            'connection_url' => 'ws://' . $_SERVER['HTTP_HOST'] . ':' . get_option('jackalopes_server_port', '8080'),
        );
        
        return rest_ensure_response($response);
    }
} 