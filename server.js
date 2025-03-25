/**
 * Jackalopes WebSocket Game Server
 * 
 * A simple WebSocket server that handles game state communication
 * for the Jackalopes multiplayer game.
 * 
 * This is a standalone server that doesn't require npm dependencies.
 * It uses the Node.js built-in modules for networking.
 */

// Use Node.js built-in modules
const http = require('http');
const fs = require('fs');
const path = require('path');
const net = require('net');

// Get port from environment variable or use default
const PORT = process.env.SERVER_PORT || 8082;

// Storage for active connections and game sessions
const clients = new Map();
const sessions = new Map();
let clientIdCounter = 1;

// Create an HTTP server for WebSocket handshake
const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('Jackalopes WebSocket Server');
});

// Log startup message
console.log(`Starting Jackalopes WebSocket Server on port ${PORT}`);
logMessage(`Server starting on port ${PORT}`);

// Handle WebSocket connections
server.on('upgrade', (req, socket, head) => {
    handleWebSocketUpgrade(req, socket, head);
});

// Start the server
server.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running at http://0.0.0.0:${PORT}/`);
    logMessage(`Server running on port ${PORT}`);
});

// Set up graceful shutdown
process.on('SIGTERM', shutdownServer);
process.on('SIGINT', shutdownServer);

/**
 * Handle WebSocket upgrade request
 */
function handleWebSocketUpgrade(req, socket, head) {
    try {
        // Parse WebSocket key from request headers
        const key = req.headers['sec-websocket-key'];
        if (!key) {
            socket.destroy();
            return;
        }

        // Accept the WebSocket connection
        const acceptKey = generateAcceptKey(key);
        const headers = [
            'HTTP/1.1 101 Switching Protocols',
            'Upgrade: websocket',
            'Connection: Upgrade',
            `Sec-WebSocket-Accept: ${acceptKey}`,
            '\r\n'
        ].join('\r\n');

        socket.write(headers);

        // Set up the connection
        const clientId = clientIdCounter++;
        clients.set(clientId, {
            socket,
            id: clientId,
            sessionId: null,
            playerName: null,
            authenticated: false
        });

        // Handle socket events
        socket.on('data', (buffer) => handleWebSocketData(clientId, buffer));
        socket.on('close', () => handleDisconnect(clientId));
        socket.on('error', (err) => {
            logMessage(`Socket error for client ${clientId}: ${err.message}`);
            socket.destroy();
        });

        // Send welcome message
        sendToClient(clientId, {
            type: 'welcome',
            server: 'Jackalopes WebSocket Server',
            timestamp: Date.now()
        });

        logMessage(`Client ${clientId} connected`);
    } catch (err) {
        logMessage(`Error in handleWebSocketUpgrade: ${err.message}`);
        socket.destroy();
    }
}

/**
 * Generate the Sec-WebSocket-Accept header value
 */
function generateAcceptKey(key) {
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const crypto = require('crypto');
    return crypto.createHash('sha1')
        .update(key + GUID, 'binary')
        .digest('base64');
}

/**
 * Handle incoming WebSocket data
 */
function handleWebSocketData(clientId, buffer) {
    try {
        const frames = decodeWebSocketFrames(buffer);
        
        for (const frame of frames) {
            if (frame.opcode === 8) { // Close frame
                handleDisconnect(clientId);
                return;
            }
            
            if (frame.opcode === 1) { // Text frame
                const message = frame.payload.toString('utf8');
                handleClientMessage(clientId, message);
            }
        }
    } catch (err) {
        logMessage(`Error processing data from client ${clientId}: ${err.message}`);
    }
}

/**
 * Decode WebSocket frames from buffer
 */
function decodeWebSocketFrames(buffer) {
    const frames = [];
    let offset = 0;
    
    while (offset < buffer.length) {
        const firstByte = buffer[offset];
        const secondByte = buffer[offset + 1];
        
        const fin = Boolean(firstByte & 0x80);
        const opcode = firstByte & 0x0F;
        const masked = Boolean(secondByte & 0x80);
        let payloadLength = secondByte & 0x7F;
        
        offset += 2;
        
        if (payloadLength === 126) {
            payloadLength = buffer.readUInt16BE(offset);
            offset += 2;
        } else if (payloadLength === 127) {
            // 64-bit length is not fully supported
            payloadLength = buffer.readUInt32BE(offset + 4);
            offset += 8;
        }
        
        let maskingKey;
        if (masked) {
            maskingKey = buffer.slice(offset, offset + 4);
            offset += 4;
        }
        
        const payload = buffer.slice(offset, offset + payloadLength);
        
        if (masked) {
            for (let i = 0; i < payload.length; i++) {
                payload[i] = payload[i] ^ maskingKey[i % 4];
            }
        }
        
        frames.push({
            fin,
            opcode,
            masked,
            payloadLength,
            payload
        });
        
        offset += payloadLength;
    }
    
    return frames;
}

/**
 * Handle client messages
 */
function handleClientMessage(clientId, message) {
    try {
        const data = JSON.parse(message);
        const client = clients.get(clientId);
        
        if (!client) {
            return;
        }
        
        logMessage(`Received ${data.type} from client ${clientId}`);
        
        switch (data.type) {
            case 'auth':
                handleAuth(clientId, data);
                break;
                
            case 'join_session':
                handleJoinSession(clientId, data);
                break;
                
            case 'player_update':
                handlePlayerUpdate(clientId, data);
                break;
                
            case 'game_event':
                handleGameEvent(clientId, data);
                break;
                
            case 'chat':
                handleChat(clientId, data);
                break;
                
            case 'leave_session':
                handleLeaveSession(clientId);
                break;
                
            default:
                sendToClient(clientId, {
                    type: 'error',
                    message: `Unknown message type: ${data.type}`
                });
                break;
        }
    } catch (err) {
        logMessage(`Error handling message from client ${clientId}: ${err.message}`);
    }
}

/**
 * Handle authentication requests
 */
function handleAuth(clientId, data) {
    const client = clients.get(clientId);
    
    if (!data.playerName) {
        sendToClient(clientId, {
            type: 'error',
            message: 'Missing playerName in auth request'
        });
        return;
    }
    
    const playerName = data.playerName.replace(/[^\w\s]/g, '');
    
    // Update client data
    client.playerName = playerName;
    client.authenticated = true;
    client.playerId = 'player_' + Math.random().toString(36).substr(2, 9);
    
    sendToClient(clientId, {
        type: 'auth_success',
        player: {
            id: client.playerId,
            name: playerName
        }
    });
    
    logMessage(`Client ${clientId} authenticated as ${playerName}`);
}

/**
 * Handle session join requests
 */
function handleJoinSession(clientId, data) {
    const client = clients.get(clientId);
    
    if (!client.authenticated) {
        sendToClient(clientId, {
            type: 'error',
            message: 'You must authenticate before joining a session'
        });
        return;
    }
    
    // Generate or use provided session key
    let sessionId;
    let sessionKey;
    
    if (data.sessionKey) {
        sessionKey = data.sessionKey;
        
        // Find session with this key
        let found = false;
        for (const [id, session] of sessions.entries()) {
            if (session.key === sessionKey) {
                sessionId = id;
                found = true;
                break;
            }
        }
        
        if (!found) {
            // Create new session with the provided key
            sessionId = 'session_' + Math.random().toString(36).substr(2, 9);
            sessions.set(sessionId, {
                key: sessionKey,
                players: new Map(),
                created: Date.now()
            });
        }
    } else {
        // Create a new session with a random key
        sessionId = 'session_' + Math.random().toString(36).substr(2, 9);
        sessionKey = Math.random().toString(36).substr(2, 9).toUpperCase();
        
        sessions.set(sessionId, {
            key: sessionKey,
            players: new Map(),
            created: Date.now()
        });
    }
    
    const session = sessions.get(sessionId);
    
    // Add player to session
    session.players.set(client.playerId, clientId);
    client.sessionId = sessionId;
    
    // Notify client
    sendToClient(clientId, {
        type: 'join_success',
        session: {
            id: sessionId,
            key: sessionKey
        },
        player: {
            id: client.playerId,
            name: client.playerName
        }
    });
    
    // Notify other players in session
    for (const [otherId, otherClientId] of session.players.entries()) {
        if (otherId !== client.playerId) {
            sendToClient(otherClientId, {
                type: 'player_joined',
                player: {
                    id: client.playerId,
                    name: client.playerName
                }
            });
        }
    }
    
    logMessage(`Client ${clientId} (${client.playerName}) joined session ${sessionId}`);
}

/**
 * Handle player update messages
 */
function handlePlayerUpdate(clientId, data) {
    const client = clients.get(clientId);
    
    if (!client || !client.authenticated || !client.sessionId) {
        return;
    }
    
    if (!data.state) {
        sendToClient(clientId, {
            type: 'error',
            message: 'Missing state in player_update'
        });
        return;
    }
    
    const session = sessions.get(client.sessionId);
    if (!session) return;
    
    // Broadcast to other players in session
    for (const [otherId, otherClientId] of session.players.entries()) {
        if (otherId !== client.playerId) {
            sendToClient(otherClientId, {
                type: 'player_update',
                player: client.playerId,
                state: data.state,
                timestamp: Date.now()
            });
        }
    }
}

/**
 * Handle game events
 */
function handleGameEvent(clientId, data) {
    const client = clients.get(clientId);
    
    if (!client || !client.authenticated || !client.sessionId) {
        return;
    }
    
    if (!data.event) {
        sendToClient(clientId, {
            type: 'error',
            message: 'Missing event in game_event'
        });
        return;
    }
    
    const session = sessions.get(client.sessionId);
    if (!session) return;
    
    // Add player and timestamp information
    const event = data.event;
    event.player = client.playerId;
    event.timestamp = Date.now();
    
    // Broadcast to all players in session (including sender)
    for (const [_, otherClientId] of session.players.entries()) {
        sendToClient(otherClientId, {
            type: 'game_event',
            event: event
        });
    }
}

/**
 * Handle chat messages
 */
function handleChat(clientId, data) {
    const client = clients.get(clientId);
    
    if (!client || !client.authenticated || !client.sessionId) {
        return;
    }
    
    if (!data.message || !data.message.trim()) {
        return;
    }
    
    const session = sessions.get(client.sessionId);
    if (!session) return;
    
    // Sanitize message
    const message = data.message.replace(/[^\w\s.!?,]/g, '');
    
    // Broadcast to all players in session
    for (const [_, otherClientId] of session.players.entries()) {
        sendToClient(otherClientId, {
            type: 'chat',
            player: client.playerId,
            playerName: client.playerName,
            message: message,
            timestamp: Date.now()
        });
    }
}

/**
 * Handle session leave requests
 */
function handleLeaveSession(clientId) {
    const client = clients.get(clientId);
    
    if (!client || !client.sessionId) {
        return;
    }
    
    const session = sessions.get(client.sessionId);
    if (!session) {
        client.sessionId = null;
        return;
    }
    
    // Remove player from session
    session.players.delete(client.playerId);
    
    // Notify other players
    for (const [_, otherClientId] of session.players.entries()) {
        sendToClient(otherClientId, {
            type: 'player_left',
            player: client.playerId,
            playerName: client.playerName
        });
    }
    
    // Clean up empty sessions
    if (session.players.size === 0) {
        sessions.delete(client.sessionId);
        logMessage(`Session ${client.sessionId} removed (empty)`);
    }
    
    logMessage(`Client ${clientId} (${client.playerName}) left session ${client.sessionId}`);
    client.sessionId = null;
}

/**
 * Handle client disconnection
 */
function handleDisconnect(clientId) {
    const client = clients.get(clientId);
    
    if (!client) {
        return;
    }
    
    // Handle session leave if in a session
    if (client.sessionId) {
        handleLeaveSession(clientId);
    }
    
    // Remove client
    clients.delete(clientId);
    logMessage(`Client ${clientId} disconnected`);
}

/**
 * Send a message to a client
 */
function sendToClient(clientId, message) {
    const client = clients.get(clientId);
    
    if (!client || !client.socket || client.socket.destroyed) {
        return;
    }
    
    try {
        const messageStr = JSON.stringify(message);
        const frame = encodeWebSocketFrame(messageStr);
        client.socket.write(frame);
    } catch (err) {
        logMessage(`Error sending to client ${clientId}: ${err.message}`);
    }
}

/**
 * Encode a message as a WebSocket frame
 */
function encodeWebSocketFrame(message) {
    const payload = Buffer.from(message);
    const payloadLength = payload.length;
    
    let header;
    let headerSize;
    
    // Determine frame header size based on payload length
    if (payloadLength <= 125) {
        header = Buffer.alloc(2);
        header[1] = payloadLength;
        headerSize = 2;
    } else if (payloadLength <= 65535) {
        header = Buffer.alloc(4);
        header[1] = 126;
        header.writeUInt16BE(payloadLength, 2);
        headerSize = 4;
    } else {
        header = Buffer.alloc(10);
        header[1] = 127;
        header.writeUInt32BE(0, 2);
        header.writeUInt32BE(payloadLength, 6);
        headerSize = 10;
    }
    
    // Set the first byte: FIN bit and opcode for text frame
    header[0] = 0x81;
    
    // Create the final buffer
    const frame = Buffer.alloc(headerSize + payloadLength);
    header.copy(frame, 0);
    payload.copy(frame, headerSize);
    
    return frame;
}

/**
 * Handle server shutdown
 */
function shutdownServer() {
    logMessage('Server shutting down...');
    
    // Close all connections
    for (const [clientId, client] of clients.entries()) {
        try {
            if (client.socket && !client.socket.destroyed) {
                client.socket.destroy();
            }
        } catch (err) {
            // Ignore errors during shutdown
        }
    }
    
    // Close the server
    server.close(() => {
        logMessage('Server stopped');
        process.exit(0);
    });
}

/**
 * Log a message to the server log file
 */
function logMessage(message) {
    const timestamp = new Date().toISOString();
    const logLine = `[${timestamp}] ${message}\n`;
    
    // Log to console
    console.log(message);
    
    // Log to file
    fs.appendFile(path.join(__dirname, 'server.log'), logLine, (err) => {
        if (err) {
            console.error('Failed to write to log file:', err);
        }
    });
} 