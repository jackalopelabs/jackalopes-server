(function($) {
    'use strict';

    $(document).ready(function() {
        // Check server status on page load
        checkServerStatus();
        
        // Setup event handlers
        setupServerControls();
        setupSessionControls();
        setupModals();
        
        // Set up polling for status updates
        setInterval(checkServerStatus, 10000); // Every 10 seconds
    });
    
    /**
     * Check the WebSocket server status
     */
    function checkServerStatus() {
        $.ajax({
            url: jackalopesServerParams.ajaxurl,
            type: 'POST',
            data: {
                action: 'jackalopes_check_server_status',
                nonce: jackalopesServerParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatusUI(response.data);
                } else {
                    console.error('Error checking server status:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
            }
        });
    }
    
    /**
     * Update the UI with server status information
     */
    function updateStatusUI(data) {
        // Update status indicators
        const statusClass = data.running ? 'status-running' : 'status-stopped';
        const statusText = data.running ? 'Running' : 'Stopped';
        
        // Update server status on settings page
        $('#server-status-indicator')
            .removeClass('status-running status-stopped status-unknown')
            .addClass(statusClass)
            .text(statusText);
            
        // Update dashboard status
        $('#dashboard-server-status')
            .removeClass('status-running status-stopped status-unknown')
            .addClass(statusClass)
            .text(statusText);
            
        // Update connection count
        if ($('#active-connections').length) {
            $('#active-connections').text(data.connections);
        }
        
        if ($('#dashboard-active-connections').length) {
            $('#dashboard-active-connections').text(data.connections);
        }
        
        // Update sessions count
        if ($('#dashboard-active-sessions').length) {
            $('#dashboard-active-sessions').text(data.sessions);
        }
        
        // Update uptime
        if (data.uptime && $('#server-uptime').length) {
            $('#server-uptime').text(data.uptime);
        }
        
        if (data.uptime && $('#dashboard-server-uptime').length) {
            $('#dashboard-server-uptime').text(data.uptime);
        }
        
        // Update recent connections if available
        if (data.recentConnections && $('#recent-connections-list').length) {
            updateRecentConnectionsList(data.recentConnections);
        }
        
        // Toggle button states based on server status
        $('.server-actions button, .quick-actions button').prop('disabled', false);
        
        if (data.running) {
            $('#start-server, #dashboard-start-server').prop('disabled', true);
        } else {
            $('#stop-server, #restart-server, #dashboard-stop-server').prop('disabled', true);
        }
    }
    
    /**
     * Update the recent connections list
     */
    function updateRecentConnectionsList(connections) {
        if (!connections.length) {
            $('#recent-connections-list').html('<tr><td colspan="4">No recent connections</td></tr>');
            return;
        }
        
        let html = '';
        connections.forEach(function(conn) {
            html += `<tr>
                <td>${conn.time}</td>
                <td>${conn.player}</td>
                <td>${conn.session}</td>
                <td>${conn.ip}</td>
            </tr>`;
        });
        
        $('#recent-connections-list').html(html);
    }
    
    /**
     * Setup server control event handlers
     */
    function setupServerControls() {
        // Start server
        $('#start-server, #dashboard-start-server').on('click', function(e) {
            e.preventDefault();
            serverAction('start');
        });
        
        // Stop server
        $('#stop-server, #dashboard-stop-server').on('click', function(e) {
            e.preventDefault();
            serverAction('stop');
        });
        
        // Restart server
        $('#restart-server').on('click', function(e) {
            e.preventDefault();
            serverAction('restart');
        });
    }
    
    /**
     * Send server action request
     */
    function serverAction(action) {
        // Disable all buttons during the action
        $('.server-actions button, .quick-actions button').prop('disabled', true);
        
        // Update status to "Processing..."
        $('#server-status-indicator, #dashboard-server-status')
            .removeClass('status-running status-stopped status-unknown')
            .addClass('status-unknown')
            .text('Processing...');
            
        $.ajax({
            url: jackalopesServerParams.ajaxurl,
            type: 'POST',
            data: {
                action: 'jackalopes_server_action',
                server_action: action,
                nonce: jackalopesServerParams.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Wait a moment then check status again
                    setTimeout(checkServerStatus, 2000);
                } else {
                    alert('Error: ' + response.data.message);
                    checkServerStatus();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                alert('An error occurred. Please check the console for details.');
                checkServerStatus();
            }
        });
    }
    
    /**
     * Setup session control event handlers
     */
    function setupSessionControls() {
        // Refresh sessions list
        $('#refresh-sessions').on('click', function(e) {
            e.preventDefault();
            location.reload();
        });
        
        // Create new session button
        $('#create-session').on('click', function(e) {
            e.preventDefault();
            $('#new-session-modal').show();
        });
        
        // Create session form submit
        $('#create-session-form').on('submit', function(e) {
            e.preventDefault();
            
            const maxPlayers = $('#max_players').val();
            let settings = $('#session_settings').val();
            
            // Validate settings JSON if not empty
            if (settings) {
                try {
                    JSON.parse(settings);
                } catch (error) {
                    alert('Invalid JSON in settings field. Please correct the format.');
                    return;
                }
            }
            
            $.ajax({
                url: jackalopesServerParams.ajaxurl,
                type: 'POST',
                data: {
                    action: 'jackalopes_create_session',
                    max_players: maxPlayers,
                    settings: settings,
                    nonce: jackalopesServerParams.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#new-session-modal').hide();
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('An error occurred. Please check the console for details.');
                }
            });
        });
        
        // View session details
        $('.view-session').on('click', function(e) {
            e.preventDefault();
            const sessionId = $(this).data('id');
            
            $('#session-details-content').html('<p>Loading...</p>');
            $('#session-modal').show();
            
            $.ajax({
                url: jackalopesServerParams.ajaxurl,
                type: 'POST',
                data: {
                    action: 'jackalopes_get_session_details',
                    session_id: sessionId,
                    nonce: jackalopesServerParams.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#session-details-content').html(response.data.html);
                    } else {
                        $('#session-details-content').html('<p>Error: ' + response.data.message + '</p>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    $('#session-details-content').html('<p>An error occurred while loading session details.</p>');
                }
            });
        });
        
        // Close session
        $('.close-session').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to close this session?')) {
                return;
            }
            
            const sessionId = $(this).data('id');
            
            $.ajax({
                url: jackalopesServerParams.ajaxurl,
                type: 'POST',
                data: {
                    action: 'jackalopes_close_session',
                    session_id: sessionId,
                    nonce: jackalopesServerParams.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('An error occurred. Please check the console for details.');
                }
            });
        });
        
        // Delete session
        $('.delete-session').on('click', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to permanently delete this session?')) {
                return;
            }
            
            const sessionId = $(this).data('id');
            
            $.ajax({
                url: jackalopesServerParams.ajaxurl,
                type: 'POST',
                data: {
                    action: 'jackalopes_delete_session',
                    session_id: sessionId,
                    nonce: jackalopesServerParams.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    alert('An error occurred. Please check the console for details.');
                }
            });
        });
    }
    
    /**
     * Setup modal event handlers
     */
    function setupModals() {
        // Close modal when clicking the X
        $('.close').on('click', function() {
            $(this).closest('.modal').hide();
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).hasClass('modal')) {
                $('.modal').hide();
            }
        });
    }
})(jQuery); 