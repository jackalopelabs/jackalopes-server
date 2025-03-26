# Jackalopes Server

WordPress plugin that provides multiplayer functionality for the Jackalopes FPS game.

## Description

Jackalopes Server is a WordPress plugin that implements a WebSocket server to enable real-time multiplayer gameplay for the Jackalopes FPS game. It handles player connections, game sessions, and state synchronization.

## Installation

### Via Composer (Recommended)

1. Add the repository to your `composer.json` file:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/yourusername/jackalopes-server"
    }
]
```

2. Require the package:

```bash
composer require jackalopes/jackalopes-server
```

3. Activate the plugin in WordPress admin.

### Manual Installation

1. Download the plugin zip file.
2. Upload to your WordPress plugins directory.
3. Run `composer install` within the plugin directory to install dependencies.
4. Activate the plugin in WordPress admin.

## Server Setup

The plugin comes with a bundled Node.js binary to eliminate external dependencies. This ensures the server can run without requiring Node.js to be installed on the host system.

### First-Time Setup

After installing the plugin for the first time, run the setup script to download the appropriate Node.js binary for your server's architecture:

```bash
cd web/app/plugins/jackalopes-server && ./bin/setup-node.sh
```

This script will:
- Detect your server's OS and architecture
- Download the appropriate Node.js binary
- Make it executable and configure it for the plugin

### For Deployments

If you've included the Node.js binary in your git repository (as configured in `.gitignore`), you won't need to perform any additional steps during deployment.

If you encounter permissions issues, you may need to set executable permissions:

```bash
chmod +x web/app/plugins/jackalopes-server/bin/node
chmod +x web/app/plugins/jackalopes-server/bin/npm
```

### WebSocket URL Configuration

For proper WebSocket connectivity, you need to configure your web server (Nginx/Apache) with a proxy to forward WebSocket connections. Here's how to set it up:

#### Nginx Configuration

Add this to your server block configuration:

```nginx
# WebSocket proxy for Jackalopes Server
location /websocket/ {
    proxy_pass http://localhost:8082;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 86400; # 24 hours
    proxy_buffering off;
}
```

#### Game Client Connection

In your game client, use the following WebSocket URL:

```
ws://yourdomain.com/websocket/
```

Note the trailing slash - it's required for the proper routing of WebSocket connections.

## Cross-Platform Deployment

The plugin includes a bundled Node.js binary for macOS systems by default. For deployment to different environments, follow these steps:

### Linux Deployment

When deploying to a Linux server:

1. **Download Linux-Compatible Node.js**:
   ```bash
   cd /path/to/jackalopes-server
   curl -sL https://nodejs.org/dist/v18.18.0/node-v18.18.0-linux-x64.tar.gz -o node-linux.tar.gz
   mkdir -p linux-bin
   tar -xzf node-linux.tar.gz -C linux-bin --strip-components=1
   rm node-linux.tar.gz
   ```

2. **Update Restart Script**:
   Edit `restart-server.sh` to use the Linux binary:
   ```bash
   # Change this line
   ./bin/node server.js > server.log 2>&1 &
   
   # To this
   ./linux-bin/bin/node server.js > server.log 2>&1 &
   ```

3. **Set Permissions**:
   ```bash
   chmod +x restart-server.sh
   chmod +x linux-bin/bin/node
   ```

### Windows Deployment

For Windows servers:

1. **Download Windows-Compatible Node.js**:
   ```bash
   # Download Node.js for Windows and extract to win-bin directory
   ```

2. **Create a Batch File Equivalent**:
   Create `restart-server.bat` with appropriate Windows commands

### Automatic Multi-Platform Support

For projects requiring deployment across multiple platforms, consider:

1. **Detection Script**: Create a setup script that detects the OS and downloads the appropriate Node.js binary
2. **Environment Variables**: Use environment variables to determine which binary to use
3. **Platform-Specific Scripts**: Maintain separate restart scripts for each platform

### Future Automation Plans

We plan to further automate the server setup process:

1. **WordPress Activation Hook**: Add an activation hook that automatically runs the Node.js setup script when the plugin is activated
2. **Deployment Integration**: Implement integration with common deployment tools (Trellis, Capistrano, etc.) to handle binary setup
3. **Self-Healing**: Add monitoring and self-repair functionality to restart the server if it stops
4. **Auto-Update**: Develop a system to automatically update the Node.js binary to the latest LTS version
5. **Multi-environment Configuration**: Provide easy configuration for different environments (development, staging, production)

These improvements will reduce manual steps and ensure the server runs smoothly across different environments.

## Configuration

After installation:

1. Navigate to "Jackalopes" in the WordPress admin menu.
2. Visit the "Settings" page to configure the WebSocket server:
   - Set the desired port (default: 8080)
   - Configure maximum connections
   - Enable auto-start if needed
   - Set logging level

## Usage

### Starting the Server

1. Go to the Jackalopes Dashboard in WordPress admin.
2. Click "Start Server" to start the WebSocket server.

### Stopping/Restarting the Server

Use the admin interface to stop or restart the server. If you need to manually stop leftover Node processes:

```bash
ps aux | grep node
kill [PID]
```

### Connecting from Game Client

Use the following WebSocket URL format to connect from your game client:

```
ws://your-wordpress-site.com/websocket/
```

## API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp-json/jackalopes/v1/status` - Get server status
- `GET /wp-json/jackalopes/v1/sessions` - List active game sessions
- `POST /wp-json/jackalopes/v1/sessions` - Create a new game session
- `POST /wp-json/jackalopes/v1/sessions/{session_key}/join` - Join an existing session

## Troubleshooting

If you encounter issues:

1. Check the plugin logs at `web/app/plugins/jackalopes-server/plugin.log`
2. Check the server logs at `web/app/plugins/jackalopes-server/server.log`
3. Verify permissions on the Node.js binary (`bin/node` should be executable)
4. Check if the port is already in use by another application

## License

GPL-2.0-or-later 