const WebSocket = require('ws');
const fs = require('fs');
const path = require('path');

// Parse command line arguments
const args = process.argv.slice(2);
const useNetworkMode = args.includes('--network');

// Check if a port is specified in the command line arguments
const portArg = args.find(arg => arg.startsWith('--port='));
const portFromArg = portArg ? parseInt(portArg.split('=')[1]) : null;

// Configure the server options
const serverOptions = {
    port: portFromArg || 8082 // Use command line port or fall back to 8082
};

// If network mode is enabled, listen on all interfaces
if (useNetworkMode) {
    serverOptions.host = '0.0.0.0';
    console.log('Network mode enabled - server accessible from other devices');
}

try {
    // Create WebSocket server
    const server = new WebSocket.Server(serverOptions);

    // Handle server errors
    server.on('error', (error) => {
        console.error('WebSocket server error:', error);
        process.exit(1);
    });

    // Handle server listening
    server.on('listening', () => {
        console.log(`WebSocket server is listening on port ${serverOptions.port}`);
    });

    const clients = new Map();
    let nextId = 0;

    // Track basic game state
    const gameState = {
      players: {},
      events: [],
      snapshots: []
    };

    // Maximum snapshots to keep in history
    const MAX_SNAPSHOTS = 60; // ~6 seconds of history at 10 snapshots per second

    // Configure simulated network conditions
    const networkConditions = {
        latency: 0, // ms of artificial latency
        packetLoss: 0 // % chance of dropping a message (0-100)
    };

    // Helper function to send with simulated network conditions
    function sendWithNetworkConditions(socket, data) {
        // Simulate packet loss
        if (Math.random() * 100 < networkConditions.packetLoss) {
            console.log('Simulating packet loss');
            return; // Drop this message
        }
        
        // Simulate latency
        if (networkConditions.latency > 0) {
            setTimeout(() => {
                socket.send(JSON.stringify(data));
            }, networkConditions.latency);
        } else {
            socket.send(JSON.stringify(data));
        }
    }

    // Function to create a server snapshot of the current game state
    function createGameSnapshot() {
      const snapshot = {
        timestamp: Date.now(),
        sequence: gameState.snapshots.length > 0 ? 
          gameState.snapshots[gameState.snapshots.length - 1].sequence + 1 : 0,
        players: JSON.parse(JSON.stringify(gameState.players)), // Deep copy
        events: gameState.events.slice() // Copy recent events
      };
      
      // Add to snapshot history
      gameState.snapshots.push(snapshot);
      
      // Limit snapshot history size
      if (gameState.snapshots.length > MAX_SNAPSHOTS) {
        gameState.snapshots.shift();
      }
      
      // Clear processed events
      gameState.events = [];
      
      return snapshot;
    }

    // Timer for regular snapshot creation and broadcast
    let snapshotInterval = null;
    const SNAPSHOT_INTERVAL_MS = 500; // Create snapshot every 500ms (2 per second)

    // Start snapshot system
    function startSnapshotSystem() {
      if (snapshotInterval) {
        clearInterval(snapshotInterval);
      }
      
      // Create and broadcast snapshots at regular interval
      snapshotInterval = setInterval(() => {
        if (clients.size > 0) {
          const snapshot = createGameSnapshot();
          
          // Broadcast to all clients
          broadcastGameSnapshot(snapshot);
        }
      }, SNAPSHOT_INTERVAL_MS);
      
      console.log(`Snapshot system started (interval: ${SNAPSHOT_INTERVAL_MS}ms)`);
    }

    // Broadcast a snapshot to all clients
    function broadcastGameSnapshot(snapshot) {
      // Skip if no clients
      if (clients.size === 0) return;
      
      for (const [clientId, client] of clients.entries()) {
        if (client.readyState === WebSocket.OPEN) {
          sendWithNetworkConditions(client, {
            type: 'game_snapshot',
            snapshot
          });
        }
      }
    }

    // Start the snapshot system
    startSnapshotSystem();

    server.on('connection', (socket) => {
      const id = nextId++;
      clients.set(id, socket);
      
      console.log(`Client ${id} connected`);
      
      // Create initial player state
      gameState.players[id] = {
        position: [0, 1, 0],
        rotation: [0, 0, 0, 1],
        health: 100
      };
      
      // Add connection event to game events
      gameState.events.push({
        type: 'player_joined',
        timestamp: Date.now(),
        data: { playerId: id }
      });
      
      // Send initial state to new player
      sendWithNetworkConditions(socket, {
        type: 'connection',
        id: id,
        gameState: gameState
      });
      
      // Broadcast to all other clients that new player joined
      for (const [clientId, client] of clients.entries()) {
        if (clientId !== id && client.readyState === WebSocket.OPEN) {
          sendWithNetworkConditions(client, {
            type: 'player_joined',
            id: id,
            initialState: gameState.players[id]
          });
        }
      }
      
      socket.on('message', (message) => {
        try {
          const data = JSON.parse(message.toString());
          
          // Handle special commands
          if (data.type === 'admin_command') {
            if (data.command === 'set_latency' && typeof data.value === 'number') {
              networkConditions.latency = Math.max(0, data.value);
              console.log(`Set simulated latency to ${networkConditions.latency}ms`);
              return;
            }
            if (data.command === 'set_packet_loss' && typeof data.value === 'number') {
              networkConditions.packetLoss = Math.max(0, Math.min(100, data.value));
              console.log(`Set simulated packet loss to ${networkConditions.packetLoss}%`);
              return;
            }
          }
          
          // Log all received messages
          console.log(`Message from client ${id}:`, data);
          
          // Handle different message types
          switch(data.type) {
            case 'player_update':
              // Extract sequence number if present for client prediction
              const sequence = data.sequence !== undefined ? data.sequence : 0;
              
              // Store the original submitted position for error calculation
              const originalPosition = data.position ? [...data.position] : null;
              
              // Get current server state for this player
              const currentServerState = gameState.players[id] ? 
                { position: [...gameState.players[id].position] } : 
                null;
              
              // Update player state in game state
              if (data.position) gameState.players[id].position = data.position;
              if (data.rotation) gameState.players[id].rotation = data.rotation;
              
              // Calculate position error if we have both states
              let positionError = 0;
              if (originalPosition && currentServerState) {
                positionError = Math.sqrt(
                  Math.pow(originalPosition[0] - currentServerState.position[0], 2) +
                  Math.pow(originalPosition[1] - currentServerState.position[1], 2) +
                  Math.pow(originalPosition[2] - currentServerState.position[2], 2)
                );
              }
              
              // Server-side correction (optional)
              // Here you can implement server-side physics validation if needed
              // For example, check if the position change is physically possible
              
              // Broadcast to all other clients
              for (const [clientId, client] of clients.entries()) {
                if (clientId !== id && client.readyState === WebSocket.OPEN) {
                  sendWithNetworkConditions(client, {
                    type: 'player_update',
                    id: id,
                    position: gameState.players[id].position,
                    rotation: gameState.players[id].rotation
                  });
                }
              }
              
              // Send an authoritative update back to the sender with sequence number
              // This helps with client-side prediction reconciliation
              sendWithNetworkConditions(socket, {
                type: 'player_update',
                id: id,
                position: gameState.players[id].position,
                rotation: gameState.players[id].rotation,
                sequence: sequence,
                timestamp: Date.now(),
                positionError: positionError,
                serverCorrection: positionError > 0.5 // Indicate if server made a major correction
              });
              break;
              
            case 'shoot':
              // Log shot event
              console.log(`Shoot event from client ${id}:`, {
                shotId: data.shotId,
                origin: data.origin,
                direction: data.direction
              });
              
              // Create a standard shot message with consistent fields
              const shotMessage = {
                type: 'shoot',
                id: id,
                shotId: data.shotId || `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`,
                origin: data.origin,
                direction: data.direction,
                timestamp: Date.now()
              };
              
              // Add to game events for snapshots
              gameState.events.push({
                type: 'player_shoot',
                timestamp: Date.now(),
                data: shotMessage
              });
              
              // Log what we're broadcasting
              console.log(`Broadcasting shot to ${clients.size} clients:`, shotMessage);
              
              // Broadcast shooting event to ALL clients including sender (for shot verification)
              for (const [clientId, client] of clients.entries()) {
                try {
                  if (client.readyState === WebSocket.OPEN) {
                    console.log(`Sending shot event to client ${clientId}`);
                    sendWithNetworkConditions(client, shotMessage);
                  } else {
                    console.log(`Client ${clientId} not ready, state: ${client.readyState}`);
                  }
                } catch (error) {
                  console.error(`Error sending shot event to client ${clientId}:`, error);
                }
              }
              break;
              
            case 'game_snapshot':
              // Process client snapshot 
              console.log(`Received game snapshot from client ${id}:`, {
                timestamp: data.snapshot.timestamp,
                sequence: data.snapshot.sequence,
                players: Object.keys(data.snapshot.players).length,
                events: data.snapshot.events?.length || 0
              });
              
              // Merge client events into server events
              if (data.snapshot.events && data.snapshot.events.length > 0) {
                // Filter and process events, add to server event queue
                data.snapshot.events.forEach(event => {
                  // Validate event before adding to the server queue
                  if (event.type && event.timestamp) {
                    // Add client ID to event data for tracking
                    const serverEvent = {
                      ...event,
                      clientId: id,
                      serverTimestamp: Date.now()
                    };
                    
                    // Add to server event queue
                    gameState.events.push(serverEvent);
                    
                    // Process specific events
                    processGameEvent(serverEvent);
                  }
                });
              }
              break;
              
            case 'ping':
              // Response immediately with pong, sending back the client's timestamp
              sendWithNetworkConditions(socket, {
                type: 'pong',
                timestamp: data.timestamp
              });
              break;
          }
        } catch (err) {
          console.error('Error processing message:', err);
        }
      });
      
      socket.on('close', () => {
        clients.delete(id);
        delete gameState.players[id];
        console.log(`Client ${id} disconnected`);
        
        // Add disconnect event
        gameState.events.push({
          type: 'player_left',
          timestamp: Date.now(),
          data: { playerId: id }
        });
        
        // Broadcast to all that player left
        for (const [clientId, client] of clients.entries()) {
          if (client.readyState === WebSocket.OPEN) {
            sendWithNetworkConditions(client, {
              type: 'player_left',
              id: id
            });
          }
        }
      });
    });

    // Process game events with server-side logic
    function processGameEvent(event) {
      switch (event.type) {
        case 'player_hit':
          // Process hit logic (e.g., damage calculation)
          console.log(`Processing hit event:`, event.data);
          
          // Update player health if hit is valid
          if (event.data.targetId && gameState.players[event.data.targetId]) {
            const targetPlayer = gameState.players[event.data.targetId];
            
            // Apply damage
            targetPlayer.health = Math.max(0, targetPlayer.health - event.data.damage || 10);
            
            // Check for player death
            if (targetPlayer.health <= 0) {
              // Create death event
              gameState.events.push({
                type: 'player_death',
                timestamp: Date.now(),
                data: {
                  playerId: event.data.targetId,
                  killedBy: event.data.attackerId
                }
              });
            }
            
            // Broadcast health update
            for (const [clientId, client] of clients.entries()) {
              if (client.readyState === WebSocket.OPEN) {
                sendWithNetworkConditions(client, {
                  type: 'player_health_update',
                  id: event.data.targetId,
                  health: targetPlayer.health
                });
              }
            }
          }
          break;
          
        // Add more event types as needed
      }
    }

    // Log server IP and port
    const os = require('os');
    const getLocalIpAddress = () => {
      const interfaces = os.networkInterfaces();
      for (const name of Object.keys(interfaces)) {
        for (const iface of interfaces[name]) {
          if (iface.family === 'IPv4' && !iface.internal) {
            return iface.address;
          }
        }
      }
      return 'localhost';
    };

    const localIp = getLocalIpAddress();
    console.log(`WebSocket server started on ${useNetworkMode ? localIp : 'localhost'}:${serverOptions.port}`);

    // Print connection instructions
    console.log(`
    To connect from this device:
    - Connect to: ws://localhost:${serverOptions.port}

    ${useNetworkMode ? `To connect from other devices:
    - Connect to: ws://${localIp}:${serverOptions.port}
    ` : ''}

    Server options:
    - Network mode: ${useNetworkMode ? 'ENABLED' : 'DISABLED'}
    - Latency simulation: ${networkConditions.latency}ms
    - Packet loss simulation: ${networkConditions.packetLoss}%
    `);

    // Cleanup on server shutdown
    process.on('SIGINT', () => {
      console.log('Shutting down server...');
      
      if (snapshotInterval) {
        clearInterval(snapshotInterval);
      }
      
      server.close(() => {
        console.log('Server closed');
        process.exit(0);
      });
    });
} catch (error) {
    console.error('Failed to create WebSocket server:', error);
    process.exit(1);
} 