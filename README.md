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

### Connecting from Game Client

Use the following WebSocket URL format to connect from your game client:

```
ws://your-wordpress-site.com:8080
```

## API Endpoints

The plugin provides the following REST API endpoints:

- `GET /wp-json/jackalopes/v1/status` - Get server status
- `GET /wp-json/jackalopes/v1/sessions` - List active game sessions
- `POST /wp-json/jackalopes/v1/sessions` - Create a new game session
- `POST /wp-json/jackalopes/v1/sessions/{session_key}/join` - Join an existing session

## License

GPL-2.0-or-later 