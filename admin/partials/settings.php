<?php
/**
 * Settings page for the plugin.
 *
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html__('Jackalopes Server Settings', 'jackalopes-server'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('jackalopes_server_settings');
        do_settings_sections('jackalopes-server-settings');
        submit_button();
        ?>
    </form>
    
    <hr>
    
    <h2><?php echo esc_html__('Server Control', 'jackalopes-server'); ?></h2>
    <div class="server-controls">
        <div class="server-status">
            <h3><?php echo esc_html__('Server Status', 'jackalopes-server'); ?></h3>
            <div id="server-status-indicator" class="status-indicator status-unknown">
                <?php echo esc_html__('Checking...', 'jackalopes-server'); ?>
            </div>
        </div>
        
        <div class="server-actions">
            <button id="start-server" class="button button-primary">
                <?php echo esc_html__('Start Server', 'jackalopes-server'); ?>
            </button>
            
            <button id="stop-server" class="button">
                <?php echo esc_html__('Stop Server', 'jackalopes-server'); ?>
            </button>
            
            <button id="restart-server" class="button">
                <?php echo esc_html__('Restart Server', 'jackalopes-server'); ?>
            </button>
        </div>
    </div>
    
    <hr>
    
    <h2><?php echo esc_html__('Server Information', 'jackalopes-server'); ?></h2>
    <table class="form-table">
        <tr>
            <th scope="row"><?php echo esc_html__('Connection URL', 'jackalopes-server'); ?></th>
            <td>
                <code id="connection-url">ws://<?php echo esc_html($_SERVER['HTTP_HOST']); ?>:<?php echo esc_html(get_option('jackalopes_server_port', '8080')); ?></code>
                <p class="description"><?php echo esc_html__('Use this URL to connect to the WebSocket server from your game.', 'jackalopes-server'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Active Connections', 'jackalopes-server'); ?></th>
            <td>
                <span id="active-connections">0</span> / <?php echo esc_html(get_option('jackalopes_server_max_connections', '100')); ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php echo esc_html__('Server Uptime', 'jackalopes-server'); ?></th>
            <td id="server-uptime">
                <?php echo esc_html__('Not Available', 'jackalopes-server'); ?>
            </td>
        </tr>
    </table>
</div>

<style>
    .server-controls {
        display: flex;
        margin: 20px 0;
    }
    
    .server-status {
        margin-right: 40px;
    }
    
    .server-actions {
        display: flex;
        align-items: center;
    }
    
    .server-actions button {
        margin-right: 10px;
    }
    
    .status-indicator {
        padding: 10px 15px;
        border-radius: 4px;
        display: inline-block;
        font-weight: bold;
    }
    
    .status-running {
        background-color: #d4edda;
        color: #155724;
    }
    
    .status-stopped {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    .status-unknown {
        background-color: #fff3cd;
        color: #856404;
    }
</style> 