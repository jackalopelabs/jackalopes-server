<?php
/**
 * Dashboard page for the plugin.
 *
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/admin/partials
 */
?>

<div class="wrap">
    <h1><?php echo esc_html__('Jackalopes Server Dashboard', 'jackalopes-server'); ?></h1>
    
    <div class="dashboard-overview">
        <div class="overview-card server-status-card">
            <h2><?php echo esc_html__('Server Status', 'jackalopes-server'); ?></h2>
            <div id="dashboard-server-status" class="status-indicator status-unknown">
                <?php echo esc_html__('Checking...', 'jackalopes-server'); ?>
            </div>
            <div class="quick-actions">
                <button id="dashboard-start-server" class="button button-primary">
                    <?php echo esc_html__('Start', 'jackalopes-server'); ?>
                </button>
                <button id="dashboard-stop-server" class="button">
                    <?php echo esc_html__('Stop', 'jackalopes-server'); ?>
                </button>
            </div>
        </div>
        
        <div class="overview-card connections-card">
            <h2><?php echo esc_html__('Active Connections', 'jackalopes-server'); ?></h2>
            <div class="connections-count">
                <span id="dashboard-active-connections">0</span>
                <span class="connections-max">/ <?php echo esc_html(get_option('jackalopes_server_max_connections', '100')); ?></span>
            </div>
        </div>
        
        <div class="overview-card sessions-card">
            <h2><?php echo esc_html__('Active Sessions', 'jackalopes-server'); ?></h2>
            <div class="sessions-count">
                <span id="dashboard-active-sessions">0</span>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=jackalopes-server-sessions')); ?>" class="button">
                <?php echo esc_html__('View All', 'jackalopes-server'); ?>
            </a>
        </div>
    </div>
    
    <div class="dashboard-details">
        <div class="dashboard-section">
            <h2><?php echo esc_html__('Recent Connections', 'jackalopes-server'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'jackalopes-server'); ?></th>
                        <th><?php echo esc_html__('Player', 'jackalopes-server'); ?></th>
                        <th><?php echo esc_html__('Session', 'jackalopes-server'); ?></th>
                        <th><?php echo esc_html__('IP', 'jackalopes-server'); ?></th>
                    </tr>
                </thead>
                <tbody id="recent-connections-list">
                    <tr>
                        <td colspan="4"><?php echo esc_html__('No recent connections', 'jackalopes-server'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="dashboard-section">
            <h2><?php echo esc_html__('Server Information', 'jackalopes-server'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Connection URL', 'jackalopes-server'); ?></th>
                    <td>
                        <code id="dashboard-connection-url">ws://<?php echo esc_html($_SERVER['HTTP_HOST']); ?>:<?php echo esc_html(get_option('jackalopes_server_port', '8080')); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Server Uptime', 'jackalopes-server'); ?></th>
                    <td id="dashboard-server-uptime">
                        <?php echo esc_html__('Not Available', 'jackalopes-server'); ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('WebSocket Port', 'jackalopes-server'); ?></th>
                    <td>
                        <?php echo esc_html(get_option('jackalopes_server_port', '8080')); ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<style>
    .dashboard-overview {
        display: flex;
        margin: 20px 0;
        gap: 20px;
    }
    
    .overview-card {
        background: white;
        border: 1px solid #ccd0d4;
        padding: 20px;
        border-radius: 5px;
        flex: 1;
        min-width: 200px;
    }
    
    .overview-card h2 {
        margin-top: 0;
    }
    
    .connections-count, .sessions-count {
        font-size: 36px;
        font-weight: bold;
        margin: 15px 0;
    }
    
    .connections-max {
        font-size: 18px;
        color: #999;
    }
    
    .quick-actions {
        margin-top: 15px;
    }
    
    .dashboard-details {
        margin-top: 30px;
    }
    
    .dashboard-section {
        background: white;
        border: 1px solid #ccd0d4;
        padding: 20px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .dashboard-section h2 {
        margin-top: 0;
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