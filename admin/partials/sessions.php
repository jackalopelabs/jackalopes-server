<?php
/**
 * Game sessions page for the plugin.
 *
 * @package    Jackalopes_Server
 * @subpackage Jackalopes_Server/admin/partials
 */

// Get game sessions from the database
global $wpdb;
$table_sessions = $wpdb->prefix . 'jackalopes_sessions';
$sessions = $wpdb->get_results("SELECT * FROM $table_sessions ORDER BY created_at DESC LIMIT 50");
?>

<div class="wrap">
    <h1><?php echo esc_html__('Game Sessions', 'jackalopes-server'); ?></h1>
    
    <div class="sessions-controls">
        <button id="create-session" class="button button-primary">
            <?php echo esc_html__('Create New Session', 'jackalopes-server'); ?>
        </button>
        
        <button id="refresh-sessions" class="button">
            <?php echo esc_html__('Refresh', 'jackalopes-server'); ?>
        </button>
    </div>
    
    <div class="sessions-list-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('ID', 'jackalopes-server'); ?></th>
                    <th><?php echo esc_html__('Session Key', 'jackalopes-server'); ?></th>
                    <th><?php echo esc_html__('Created', 'jackalopes-server'); ?></th>
                    <th><?php echo esc_html__('Status', 'jackalopes-server'); ?></th>
                    <th><?php echo esc_html__('Players', 'jackalopes-server'); ?></th>
                    <th><?php echo esc_html__('Actions', 'jackalopes-server'); ?></th>
                </tr>
            </thead>
            <tbody id="sessions-list">
                <?php if (empty($sessions)) : ?>
                    <tr>
                        <td colspan="6"><?php echo esc_html__('No game sessions found.', 'jackalopes-server'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($sessions as $session) : ?>
                        <tr>
                            <td><?php echo esc_html($session->id); ?></td>
                            <td><?php echo esc_html($session->session_key); ?></td>
                            <td><?php echo esc_html(human_time_diff(strtotime($session->created_at), current_time('timestamp')) . ' ago'); ?></td>
                            <td>
                                <span class="session-status session-status-<?php echo esc_attr($session->status); ?>">
                                    <?php echo esc_html($session->status); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($session->current_players . '/' . $session->max_players); ?></td>
                            <td>
                                <a href="#" class="view-session" data-id="<?php echo esc_attr($session->id); ?>">
                                    <?php echo esc_html__('View', 'jackalopes-server'); ?>
                                </a> | 
                                <?php if ($session->status === 'active') : ?>
                                    <a href="#" class="close-session" data-id="<?php echo esc_attr($session->id); ?>">
                                        <?php echo esc_html__('Close', 'jackalopes-server'); ?>
                                    </a>
                                <?php else : ?>
                                    <a href="#" class="delete-session" data-id="<?php echo esc_attr($session->id); ?>">
                                        <?php echo esc_html__('Delete', 'jackalopes-server'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Session Details Modal -->
    <div id="session-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><?php echo esc_html__('Session Details', 'jackalopes-server'); ?></h2>
            
            <div id="session-details-content">
                <p><?php echo esc_html__('Loading...', 'jackalopes-server'); ?></p>
            </div>
        </div>
    </div>
    
    <!-- New Session Modal -->
    <div id="new-session-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><?php echo esc_html__('Create New Session', 'jackalopes-server'); ?></h2>
            
            <form id="create-session-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="max_players"><?php echo esc_html__('Max Players', 'jackalopes-server'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="max_players" name="max_players" min="2" max="100" value="16">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="session_settings"><?php echo esc_html__('Session Settings (JSON)', 'jackalopes-server'); ?></label>
                        </th>
                        <td>
                            <textarea id="session_settings" name="session_settings" rows="6" class="large-text"></textarea>
                            <p class="description"><?php echo esc_html__('Optional JSON settings for the game session.', 'jackalopes-server'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="submit-container">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php echo esc_attr__('Create Session', 'jackalopes-server'); ?>">
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .sessions-controls {
        margin: 20px 0;
    }
    
    .sessions-controls button {
        margin-right: 10px;
    }
    
    .session-status {
        padding: 5px 10px;
        border-radius: 4px;
        display: inline-block;
        font-weight: bold;
    }
    
    .session-status-active {
        background-color: #d4edda;
        color: #155724;
    }
    
    .session-status-closed {
        background-color: #f8d7da;
        color: #721c24;
    }
    
    /* Modal Styles */
    .modal {
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 800px;
        border-radius: 5px;
    }
    
    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
    }
    
    .close:hover {
        color: black;
    }
    
    .submit-container {
        margin-top: 20px;
    }
</style> 