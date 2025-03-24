<?php
/**
 * WebSocket Server implementation using Ratchet
 *
 * @package    Jackalopes_Server
 */

// Initialize WordPress using our custom loader
require_once dirname(dirname(dirname(__FILE__))) . '/wordpress-loader.php';

// Load Composer dependencies
require_once JACKALOPES_SERVER_PLUGIN_DIR . 'vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

/**
 * Jackalopes WebSocket Server Component
 */
class JackalopesServerComponent implements MessageComponentInterface {
    protected $clients;
    protected $sessions = [];
    protected $playerToSession = [];
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
        
        // Log server startup
        $this->log('WebSocket server started');
    }
    
    /**
     * Handle new connections
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->playerData = null;
        $conn->sessionId = null;
        
        $this->log('New connection: ' . $conn->resourceId);
        
        // Send welcome message with server info
        $conn->send(json_encode([
            'type' => 'welcome',
            'server' => 'Jackalopes WebSocket Server',
            'version' => JACKALOPES_SERVER_VERSION,
            'timestamp' => time(),
        ]));
    }
    
    /**
     * Handle incoming messages
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        // Handle malformed messages
        if (!is_array($data) || !isset($data['type'])) {
            $from->send(json_encode([
                'type' => 'error',
                'message' => 'Invalid message format'
            ]));
            return;
        }
        
        $this->log('Message received: ' . $data['type']);
        
        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
                
            case 'join_session':
                $this->handleJoinSession($from, $data);
                break;
                
            case 'player_update':
                $this->handlePlayerUpdate($from, $data);
                break;
                
            case 'game_event':
                $this->handleGameEvent($from, $data);
                break;
                
            case 'chat':
                $this->handleChat($from, $data);
                break;
                
            case 'leave_session':
                $this->handleLeaveSession($from);
                break;
                
            default:
                $from->send(json_encode([
                    'type' => 'error',
                    'message' => 'Unknown message type: ' . $data['type']
                ]));
                break;
        }
    }
    
    /**
     * Handle authentication requests
     */
    private function handleAuth(ConnectionInterface $client, $data) {
        // In a production environment, this would validate against WordPress users
        // For now, just accept the player name
        
        if (!isset($data['playerName'])) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'Missing playerName in auth request'
            ]));
            return;
        }
        
        $playerName = sanitize_text_field($data['playerName']);
        
        // Create player data
        $client->playerData = [
            'id' => uniqid('player_'),
            'name' => $playerName,
            'authenticated' => true,
        ];
        
        $client->send(json_encode([
            'type' => 'auth_success',
            'player' => $client->playerData,
        ]));
        
        $this->log('Player authenticated: ' . $playerName);
    }
    
    /**
     * Handle session join requests
     */
    private function handleJoinSession(ConnectionInterface $client, $data) {
        if (!$client->playerData) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'You must authenticate before joining a session'
            ]));
            return;
        }
        
        // Check if session key is provided
        if (isset($data['sessionKey'])) {
            $sessionKey = $data['sessionKey'];
            
            // Check if session exists in database
            global $wpdb;
            $table_sessions = $wpdb->prefix . 'jackalopes_sessions';
            $session = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_sessions WHERE session_key = %s AND status = 'active'",
                $sessionKey
            ));
            
            if (!$session) {
                $client->send(json_encode([
                    'type' => 'error',
                    'message' => 'Session not found or not active'
                ]));
                return;
            }
            
            $sessionId = $session->id;
        } else {
            // No session key, create a new session
            $result = Jackalopes_Server_Database::create_session();
            
            if (!$result['success']) {
                $client->send(json_encode([
                    'type' => 'error',
                    'message' => $result['message']
                ]));
                return;
            }
            
            $sessionId = $result['session']['id'];
            $sessionKey = $result['session']['session_key'];
        }
        
        // Register player in the session
        $playerId = $client->playerData['id'];
        $playerName = $client->playerData['name'];
        
        // Add player to database
        $result = Jackalopes_Server_Database::add_player($sessionId, $playerName);
        
        if (!$result['success']) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => $result['message']
            ]));
            return;
        }
        
        // Associate client with session
        $client->sessionId = $sessionId;
        $client->playerData['dbId'] = $result['player']['id'];
        
        // Initialize session tracking if needed
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [
                'players' => []
            ];
        }
        
        // Add player to session
        $this->sessions[$sessionId]['players'][$playerId] = $client;
        $this->playerToSession[$playerId] = $sessionId;
        
        // Send success message to client
        $client->send(json_encode([
            'type' => 'join_success',
            'session' => [
                'id' => $sessionId,
                'key' => $sessionKey,
            ],
            'player' => $client->playerData,
        ]));
        
        // Notify other players in session
        $this->broadcastToSession($sessionId, [
            'type' => 'player_joined',
            'player' => [
                'id' => $playerId,
                'name' => $playerName,
            ]
        ], $client);
        
        $this->log('Player joined session: ' . $playerName . ' -> ' . $sessionId);
    }
    
    /**
     * Handle player update messages
     */
    private function handlePlayerUpdate(ConnectionInterface $client, $data) {
        if (!$client->playerData || !$client->sessionId) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'You must join a session before sending updates'
            ]));
            return;
        }
        
        $sessionId = $client->sessionId;
        $playerId = $client->playerData['id'];
        
        // Update last activity timestamp
        Jackalopes_Server_Database::update_player_activity($client->playerData['dbId']);
        
        // Validate update data
        if (!isset($data['state'])) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'Missing state in player_update'
            ]));
            return;
        }
        
        // Forward update to other players in session
        $this->broadcastToSession($sessionId, [
            'type' => 'player_update',
            'player' => $playerId,
            'state' => $data['state'],
            'timestamp' => time(),
        ], $client);
    }
    
    /**
     * Handle game events
     */
    private function handleGameEvent(ConnectionInterface $client, $data) {
        if (!$client->playerData || !$client->sessionId) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'You must join a session before sending game events'
            ]));
            return;
        }
        
        $sessionId = $client->sessionId;
        $playerId = $client->playerData['id'];
        
        // Validate event data
        if (!isset($data['event'])) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'Missing event in game_event'
            ]));
            return;
        }
        
        // Add player and timestamp information
        $event = $data['event'];
        $event['player'] = $playerId;
        $event['timestamp'] = time();
        
        // Forward event to all players in session (including sender)
        $this->broadcastToSession($sessionId, [
            'type' => 'game_event',
            'event' => $event,
        ]);
        
        // For specific important events, save to database
        if (isset($event['type']) && $event['type'] === 'shot') {
            $this->logGameEvent($sessionId, 'shot', $playerId, $event);
        }
    }
    
    /**
     * Handle chat messages
     */
    private function handleChat(ConnectionInterface $client, $data) {
        if (!$client->playerData || !$client->sessionId) {
            $client->send(json_encode([
                'type' => 'error',
                'message' => 'You must join a session before sending chat messages'
            ]));
            return;
        }
        
        $sessionId = $client->sessionId;
        $playerId = $client->playerData['id'];
        $playerName = $client->playerData['name'];
        
        // Validate message
        if (!isset($data['message']) || trim($data['message']) === '') {
            return;
        }
        
        $message = sanitize_text_field($data['message']);
        
        // Forward message to all players in session
        $this->broadcastToSession($sessionId, [
            'type' => 'chat',
            'player' => $playerId,
            'playerName' => $playerName,
            'message' => $message,
            'timestamp' => time(),
        ]);
    }
    
    /**
     * Handle session leave requests
     */
    private function handleLeaveSession(ConnectionInterface $client) {
        if (!$client->playerData || !$client->sessionId) {
            return;
        }
        
        $sessionId = $client->sessionId;
        $playerId = $client->playerData['id'];
        $playerName = $client->playerData['name'];
        
        // Remove player from session tracking
        if (isset($this->sessions[$sessionId]['players'][$playerId])) {
            unset($this->sessions[$sessionId]['players'][$playerId]);
        }
        
        if (isset($this->playerToSession[$playerId])) {
            unset($this->playerToSession[$playerId]);
        }
        
        // Notify other players
        $this->broadcastToSession($sessionId, [
            'type' => 'player_left',
            'player' => $playerId,
            'playerName' => $playerName,
        ]);
        
        // Reset session data
        $client->sessionId = null;
        
        $this->log('Player left session: ' . $playerName . ' -> ' . $sessionId);
    }
    
    /**
     * Handle closed connections
     */
    public function onClose(ConnectionInterface $conn) {
        // Handle session leave if applicable
        if ($conn->playerData && $conn->sessionId) {
            $this->handleLeaveSession($conn);
        }
        
        // Remove from clients list
        $this->clients->detach($conn);
        
        $this->log('Connection closed: ' . $conn->resourceId);
    }
    
    /**
     * Handle connection errors
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->log('Error: ' . $e->getMessage());
        
        $conn->close();
    }
    
    /**
     * Broadcast a message to all players in a session
     */
    private function broadcastToSession($sessionId, $message, $exclude = null) {
        if (!isset($this->sessions[$sessionId])) {
            return;
        }
        
        $encoded = json_encode($message);
        
        foreach ($this->sessions[$sessionId]['players'] as $playerId => $client) {
            if ($exclude !== null && $client === $exclude) {
                continue;
            }
            
            $client->send($encoded);
        }
    }
    
    /**
     * Log game events to the database
     */
    private function logGameEvent($sessionId, $eventType, $playerId, $eventData) {
        // For now, just log to the plugin log
        $this->log("Game event: $eventType by $playerId in session $sessionId");
        
        // In a production environment, this would save to the database
        // Jackalopes_Server_Database::save_event($sessionId, $eventType, $playerId, json_encode($eventData));
    }
    
    /**
     * Log a message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] $message\n";
        
        file_put_contents(
            JACKALOPES_SERVER_PLUGIN_DIR . 'server.log',
            $log_entry,
            FILE_APPEND
        );
    }
}

// Parse command line arguments
$options = getopt('', ['port:']);
$port = isset($options['port']) ? (int)$options['port'] : 8080;

// Start the server
$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new JackalopesServerComponent()
        )
    ),
    $port
);

$server->run();