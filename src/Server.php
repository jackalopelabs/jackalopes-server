<?php
/**
 * Main server class for Ratchet WebSocket implementation.
 *
 * @package    Jackalopes\Server
 */

namespace Jackalopes\Server;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;

class Server implements MessageComponentInterface {
    /**
     * Connected clients
     *
     * @var \SplObjectStorage
     */
    protected $clients;
    
    /**
     * Active game sessions
     *
     * @var array
     */
    protected $sessions;
    
    /**
     * Logger instance
     *
     * @var Logger
     */
    protected $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->clients = new \SplObjectStorage();
        $this->sessions = [];
        $this->logger = new Logger();
        
        // Load active sessions from database
        $this->loadSessionsFromDatabase();
    }
    
    /**
     * Load active sessions from the database
     */
    protected function loadSessionsFromDatabase() {
        if (class_exists('Jackalopes_Server_Database')) {
            $db_sessions = \Jackalopes_Server_Database::get_active_sessions();
            foreach ($db_sessions as $session) {
                $this->sessions[$session->session_key] = [
                    'id' => $session->id,
                    'players' => [],
                    'max_players' => $session->max_players,
                    'settings' => json_decode($session->settings ?: '{}', true),
                ];
            }
        }
    }
    
    /**
     * When a client connects
     *
     * @param ConnectionInterface $conn
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->playerData = null;
        $conn->sessionKey = null;
        
        $this->logger->info("New connection: {$conn->resourceId}");
    }
    
    /**
     * When a client sends a message
     *
     * @param ConnectionInterface $from
     * @param string $msg
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        
        if (!$data || !isset($data['type'])) {
            $this->logger->error("Invalid message format from {$from->resourceId}");
            return;
        }
        
        switch ($data['type']) {
            case 'auth':
                $this->handleAuth($from, $data);
                break;
                
            case 'game_update':
                $this->handleGameUpdate($from, $data);
                break;
                
            case 'chat':
                $this->handleChat($from, $data);
                break;
                
            default:
                $this->logger->warning("Unknown message type: {$data['type']}");
                break;
        }
    }
    
    /**
     * Handle authentication/session joining
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleAuth($conn, $data) {
        if (!isset($data['session_key']) || !isset($data['player_key'])) {
            $this->sendError($conn, 'Invalid auth data');
            return;
        }
        
        $sessionKey = $data['session_key'];
        $playerKey = $data['player_key'];
        
        // Verify session exists
        if (!isset($this->sessions[$sessionKey])) {
            $this->sendError($conn, 'Session not found');
            return;
        }
        
        // Verify player in database
        if (class_exists('Jackalopes_Server_Database')) {
            $session = \Jackalopes_Server_Database::get_session_by_key($sessionKey);
            if (!$session) {
                $this->sendError($conn, 'Session not found in database');
                return;
            }
            
            // TODO: Verify player key belongs to this session
        }
        
        // Store player data with connection
        $conn->playerData = [
            'key' => $playerKey,
            'name' => $data['player_name'] ?? 'Player',
        ];
        $conn->sessionKey = $sessionKey;
        
        // Add player to session
        $this->sessions[$sessionKey]['players'][$playerKey] = [
            'name' => $conn->playerData['name'],
            'connection' => $conn,
        ];
        
        // Send success response
        $this->sendToClient($conn, [
            'type' => 'auth_success',
            'session' => [
                'key' => $sessionKey,
                'player_count' => count($this->sessions[$sessionKey]['players']),
            ],
        ]);
        
        // Notify other players in session
        $this->broadcastToSession($sessionKey, [
            'type' => 'player_joined',
            'player' => [
                'key' => $playerKey,
                'name' => $conn->playerData['name'],
            ],
        ], $conn);
        
        $this->logger->info("Player {$conn->playerData['name']} joined session {$sessionKey}");
    }
    
    /**
     * Handle game state update
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleGameUpdate($conn, $data) {
        if (!$conn->sessionKey || !$conn->playerData) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }
        
        if (!isset($data['snapshot'])) {
            $this->sendError($conn, 'Invalid game update data');
            return;
        }
        
        // Broadcast update to all players in session
        $this->broadcastToSession($conn->sessionKey, [
            'type' => 'game_update',
            'player_key' => $conn->playerData['key'],
            'snapshot' => $data['snapshot'],
        ], $conn);
        
        // Optionally save snapshot to database
        if (isset($data['save_snapshot']) && $data['save_snapshot'] && class_exists('Jackalopes_Server_Database')) {
            $session = \Jackalopes_Server_Database::get_session_by_key($conn->sessionKey);
            if ($session) {
                \Jackalopes_Server_Database::save_snapshot(
                    $session->id,
                    json_encode($data['snapshot'])
                );
            }
        }
    }
    
    /**
     * Handle chat message
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function handleChat($conn, $data) {
        if (!$conn->sessionKey || !$conn->playerData) {
            $this->sendError($conn, 'Not authenticated');
            return;
        }
        
        if (!isset($data['message'])) {
            $this->sendError($conn, 'Invalid chat data');
            return;
        }
        
        // Broadcast chat to all players in session
        $this->broadcastToSession($conn->sessionKey, [
            'type' => 'chat',
            'player_key' => $conn->playerData['key'],
            'player_name' => $conn->playerData['name'],
            'message' => $data['message'],
        ]);
    }
    
    /**
     * When a client disconnects
     *
     * @param ConnectionInterface $conn
     */
    public function onClose(ConnectionInterface $conn) {
        // Remove from session if authenticated
        if ($conn->sessionKey && $conn->playerData) {
            $sessionKey = $conn->sessionKey;
            $playerKey = $conn->playerData['key'];
            
            if (isset($this->sessions[$sessionKey]['players'][$playerKey])) {
                unset($this->sessions[$sessionKey]['players'][$playerKey]);
                
                // Notify other players in session
                $this->broadcastToSession($sessionKey, [
                    'type' => 'player_left',
                    'player_key' => $playerKey,
                ]);
                
                $this->logger->info("Player {$conn->playerData['name']} left session {$sessionKey}");
            }
        }
        
        // Detach connection
        $this->clients->detach($conn);
        
        $this->logger->info("Connection {$conn->resourceId} disconnected");
    }
    
    /**
     * Error handler
     *
     * @param ConnectionInterface $conn
     * @param \Exception $e
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("Error: {$e->getMessage()}");
        $conn->close();
    }
    
    /**
     * Send message to specific client
     *
     * @param ConnectionInterface $conn
     * @param array $data
     */
    protected function sendToClient(ConnectionInterface $conn, array $data) {
        $conn->send(json_encode($data));
    }
    
    /**
     * Send error message to client
     *
     * @param ConnectionInterface $conn
     * @param string $message
     */
    protected function sendError(ConnectionInterface $conn, string $message) {
        $this->sendToClient($conn, [
            'type' => 'error',
            'message' => $message,
        ]);
    }
    
    /**
     * Broadcast message to all players in a session
     *
     * @param string $sessionKey
     * @param array $data
     * @param ConnectionInterface|null $except
     */
    protected function broadcastToSession(string $sessionKey, array $data, ConnectionInterface $except = null) {
        if (!isset($this->sessions[$sessionKey])) {
            return;
        }
        
        $encoded = json_encode($data);
        
        foreach ($this->sessions[$sessionKey]['players'] as $player) {
            $conn = $player['connection'];
            if ($except !== $conn) {
                $conn->send($encoded);
            }
        }
    }
    
    /**
     * Run the server
     *
     * @param int $port
     */
    public static function run($port = 8080) {
        $server = new self();
        
        $wsServer = new WsServer($server);
        $httpServer = new HttpServer($wsServer);
        
        $ioServer = IoServer::factory(
            $httpServer,
            $port
        );
        
        echo "Server running on port {$port}\n";
        $server->logger->info("Server started on port {$port}");
        
        $ioServer->run();
    }
} 