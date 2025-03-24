// Test script for the Jackalopes Multiplayer Server
const { exec } = require('child_process');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Get local IP address for testing on multiple devices
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

console.log(`
======================================
Jackalopes Multiplayer Testing Server
======================================

Local IP: ${localIp}

To test the multiplayer functionality:

1. Start the WebSocket server:
   $ node server.js

2. In a separate terminal, start the game:
   $ npm run dev

3. Open the game in multiple browser windows to see the
   multiplayer functionality in action.

4. To test on multiple devices on your local network:
   - Update the connection URL in ConnectionManager.ts:
     const serverUrl = 'ws://${localIp}:8080'
   - Start the server with host set to '0.0.0.0':
     Edit server.js to use { host: '0.0.0.0', port: 8080 }
   - Ensure your firewall allows connections on port 8080

5. Use the HTML test client to verify connectivity:
   Open test-client.html in a web browser

======================================
`);

// Check if server is already running
const isPortInUse = (port) => {
  return new Promise((resolve) => {
    const server = require('net').createServer();
    server.once('error', () => resolve(true));
    server.once('listening', () => {
      server.close();
      resolve(false);
    });
    server.listen(port);
  });
};

// Start the server if requested
if (process.argv.includes('--start')) {
  isPortInUse(8080).then((inUse) => {
    if (inUse) {
      console.log('Server already running on port 8080');
    } else {
      console.log('Starting server...');
      const server = exec('node server.js');
      server.stdout.pipe(process.stdout);
      server.stderr.pipe(process.stderr);
      console.log('Server started on port 8080');
    }
  });
} 