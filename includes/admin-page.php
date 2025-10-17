<?php
/**
 * Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<style>
.surf-admin-dashboard {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.surf-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.surf-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
}

.surf-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    opacity: 0.9;
}

.surf-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 0;
}

.surf-connection-status {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #007cba;
}

.surf-status-indicator {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.surf-status-indicator.connected {
    background: #d4edda;
    color: #155724;
}

.surf-status-indicator.disconnected {
    background: #f8d7da;
    color: #721c24;
}

.surf-settings-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
    margin-bottom: 20px;
}

.surf-settings-section h2 {
    margin-top: 0;
    color: #1d2327;
    border-bottom: 2px solid #007cba;
    padding-bottom: 10px;
}

.surf-help-section {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 20px;
}

.surf-help-section h3 {
    color: #1d2327;
    margin-top: 0;
}

.surf-help-section ol {
    line-height: 1.6;
}

.surf-help-section code {
    background: #f1f1f1;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}

/* User Submissions Table Styles */
.surf-user-submissions-table-container {
    margin-top: 20px;
}

.surf-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e1e5e9;
}

.surf-table-header h3 {
    margin: 0;
    color: #1d2327;
    font-size: 18px;
}

.surf-table-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.surf-refresh-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    background: #007cba;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
    transition: background-color 0.2s;
}

.surf-refresh-btn:hover {
    background: #005a87;
}

.surf-table-wrapper {
    overflow-x: auto;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    background: white;
}

.surf-user-submissions-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.surf-user-submissions-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #1d2327;
    border-bottom: 2px solid #e1e5e9;
    white-space: nowrap;
}

.surf-user-submissions-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f1;
    vertical-align: top;
}

.surf-user-submissions-table tbody tr:hover {
    background: #f8f9fa;
}

.surf-user-submissions-table tbody tr:last-child td {
    border-bottom: none;
}

.surf-loading {
    text-align: center;
    color: #666;
    font-style: italic;
    padding: 20px !important;
}

.surf-table-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 15px;
    padding: 10px 0;
}

.surf-pagination-btn {
    padding: 8px 16px;
    background: #f8f9fa;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.surf-pagination-btn:hover:not(:disabled) {
    background: #e9ecef;
    border-color: #007cba;
}

.surf-pagination-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.surf-page-info {
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .surf-table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .surf-user-submissions-table th,
    .surf-user-submissions-table td {
        padding: 8px 10px;
        font-size: 13px;
    }
}
</style>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="surf-admin-dashboard">
        <h2>📊 Dashboard</h2>
        
        <div class="surf-stats-grid">
            <div class="surf-stat-card">
                <h3>Total Messages</h3>
                <span class="surf-stat-number" id="total-messages">-</span>
            </div>
            <div class="surf-stat-card">
                <h3>Messages Today</h3>
                <span class="surf-stat-number" id="messages-today">-</span>
            </div>
            <div class="surf-stat-card">
                <h3>Active Users</h3>
                <span class="surf-stat-number" id="active-users">-</span>
            </div>
            <div class="surf-stat-card">
                <h3>Support Tickets</h3>
                <span class="surf-stat-number" id="support-tickets">-</span>
            </div>
            <div class="surf-stat-card">
                <h3>User Submissions</h3>
                <span class="surf-stat-number" id="user-submissions">-</span>
            </div>
        </div>
        
        <div class="surf-connection-status">
            <h3>Connection Status</h3>
            <div class="surf-status-indicator" id="connection-status">
                Checking...
            </div>
        </div>
    </div>
    
    <div class="surf-settings-section">
        <h2>👥 User Submissions</h2>
        
        <div class="surf-user-submissions-table-container">
            <div class="surf-table-header">
                <h3>Users who have entered their information</h3>
                <div class="surf-table-controls">
                    <button class="surf-refresh-btn" id="refresh-submissions">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M1 4V10H7M23 20V14H17M20.49 9A9 9 0 0 0 5.64 5.64L1 10M23 14L18.36 18.36A9 9 0 0 1 3.51 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
            
            <div class="surf-table-wrapper">
                <table class="surf-user-submissions-table" id="surf-user-submissions-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Date Entered</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody id="surf-submissions-tbody">
                        <tr>
                            <td colspan="4" class="surf-loading">Loading user submissions...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="surf-table-pagination" id="surf-submissions-pagination" style="display: none;">
                <button class="surf-pagination-btn" id="prev-page" disabled>Previous</button>
                <span class="surf-page-info" id="page-info">Page 1 of 1</span>
                <button class="surf-pagination-btn" id="next-page" disabled>Next</button>
            </div>
        </div>
    </div>
    
    <div class="surf-settings-section">
        <h2>⚙️ Configuration</h2>
        
        <div class="notice notice-info">
            <p><strong>✅ Pre-configured with your Pusher credentials:</strong></p>
            <ul>
                <li><strong>App ID:</strong> 2064913</li>
                <li><strong>Key:</strong> c08d09b4013a00d6a626</li>
                <li><strong>Secret:</strong> 15a73a9dbb0a4884f6fa</li>
                <li><strong>Cluster:</strong> us3</li>
            </ul>
            <p>These are automatically set as defaults. You can modify them below if needed.</p>
        </div>
        
        <form method="post" action="options.php">
            <?php settings_fields('surf_social_settings'); ?>
            <?php do_settings_sections('surf_social_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="surf_social_enabled"><?php _e('Enable Surf Social', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="surf_social_enabled" id="surf_social_enabled" value="1" <?php checked(get_option('surf_social_enabled'), '1'); ?>>
                        <p class="description"><?php _e('Enable the real-time presence and chat features', 'surf-social'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="surf_social_use_pusher"><?php _e('Use Pusher', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" name="surf_social_use_pusher" id="surf_social_use_pusher" value="1" <?php checked(get_option('surf_social_use_pusher'), '1'); ?>>
                        <p class="description"><?php _e('Use Pusher for real-time communication (recommended)', 'surf-social'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="surf_social_pusher_key"><?php _e('Pusher Key', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="surf_social_pusher_key" id="surf_social_pusher_key" value="<?php echo esc_attr(get_option('surf_social_pusher_key')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your Pusher app key', 'surf-social'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="surf_social_pusher_secret"><?php _e('Pusher Secret', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="password" name="surf_social_pusher_secret" id="surf_social_pusher_secret" value="<?php echo esc_attr(get_option('surf_social_pusher_secret')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your Pusher app secret (required for private channels)', 'surf-social'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="surf_social_pusher_cluster"><?php _e('Pusher Cluster', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="surf_social_pusher_cluster" id="surf_social_pusher_cluster" value="<?php echo esc_attr(get_option('surf_social_pusher_cluster', 'us2')); ?>" class="regular-text">
                        <p class="description"><?php _e('Your Pusher cluster (e.g., us2, eu, ap1)', 'surf-social'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="surf_social_websocket_url"><?php _e('WebSocket URL (Fallback)', 'surf-social'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="surf_social_websocket_url" id="surf_social_websocket_url" value="<?php echo esc_attr(get_option('surf_social_websocket_url')); ?>" class="regular-text">
                        <p class="description"><?php _e('WebSocket server URL for fallback (e.g., ws://localhost:8080)', 'surf-social'); ?></p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
    </div>
    
    <div class="surf-help-section">
        <h2>🚀 Getting Started</h2>
        
        <h3>Option 1: Using Pusher (Recommended)</h3>
        <ol>
            <li><?php _e('Sign up for a free Pusher account at', 'surf-social'); ?> <a href="https://pusher.com" target="_blank">pusher.com</a></li>
            <li><?php _e('Create a new app and copy your key and cluster', 'surf-social'); ?></li>
            <li><?php _e('Enter your Pusher credentials above', 'surf-social'); ?></li>
            <li><?php _e('Enable Surf Social and save', 'surf-social'); ?></li>
        </ol>
        
        <h3>Option 2: Using WebSocket Fallback</h3>
        <p><?php _e('If you prefer to use your own WebSocket server, you can find the fallback server in the plugin directory:', 'surf-social'); ?></p>
        <code>surf-social/websocket-server/server.js</code>
        <p><?php _e('To run it:', 'surf-social'); ?></p>
        <ol>
            <li><?php _e('Install Node.js', 'surf-social'); ?></li>
            <li><?php _e('Run', 'surf-social'); ?> <code>npm install</code></li>
            <li><?php _e('Run', 'surf-social'); ?> <code>node server.js</code></li>
            <li><?php _e('Enter', 'surf-social'); ?> <code>ws://localhost:8080</code> <?php _e('in the WebSocket URL field', 'surf-social'); ?></li>
        </ol>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    let currentPage = 1;
    const perPage = 10;
    
    // Load stats and user submissions on page load
    loadStats();
    loadUserSubmissions();
    
    function loadStats() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'surf_social_get_stats',
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const stats = response.data;
                    $('#total-messages').text(stats.total_web_messages || 0);
                    $('#messages-today').text(stats.messages_today || 0);
                    $('#active-users').text(stats.unique_users || 0);
                    $('#support-tickets').text(stats.active_support_tickets || 0);
                    $('#user-submissions').text(stats.user_submissions || 0);
                    
                    // Update connection status
                    const pusherKey = $('#surf_social_pusher_key').val();
                    const websocketUrl = $('#surf_social_websocket_url').val();
                    const usePusher = $('#surf_social_use_pusher').is(':checked');
                    
                    if ((usePusher && pusherKey) || (!usePusher && websocketUrl)) {
                        $('#connection-status').removeClass('disconnected').addClass('connected').text('Configured');
                    } else {
                        $('#connection-status').removeClass('connected').addClass('disconnected').text('Not Configured');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Stats AJAX Error:', xhr.responseText);
                $('#connection-status').removeClass('connected').addClass('disconnected').text('Error Loading');
            }
        });
    }
    
    function loadUserSubmissions(page = 1) {
        currentPage = page;
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'surf_social_get_user_submissions',
                page: page,
                per_page: perPage,
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayUserSubmissions(response.data);
                } else {
                    $('#surf-submissions-tbody').html('<tr><td colspan="4" class="surf-loading">Error loading user submissions</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('User Submissions AJAX Error:', xhr.responseText);
                $('#surf-submissions-tbody').html('<tr><td colspan="4" class="surf-loading">Error loading user submissions</td></tr>');
            }
        });
    }
    
    function displayUserSubmissions(data) {
        const tbody = $('#surf-submissions-tbody');
        
        if (data.submissions && data.submissions.length > 0) {
            let html = '';
            data.submissions.forEach(function(submission) {
                const createdDate = new Date(submission.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const updatedDate = submission.updated_at && submission.updated_at !== submission.created_at 
                    ? new Date(submission.updated_at).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    })
                    : '-';
                
                html += `
                    <tr>
                        <td><strong>${submission.name}</strong></td>
                        <td>${submission.email}</td>
                        <td>${createdDate}</td>
                        <td>${updatedDate}</td>
                    </tr>
                `;
            });
            tbody.html(html);
            
            // Update pagination
            updatePagination(data.total_pages, data.current_page);
        } else {
            tbody.html('<tr><td colspan="4" class="surf-loading">No user submissions found</td></tr>');
            $('#surf-submissions-pagination').hide();
        }
    }
    
    function updatePagination(totalPages, currentPage) {
        const pagination = $('#surf-submissions-pagination');
        const prevBtn = $('#prev-page');
        const nextBtn = $('#next-page');
        const pageInfo = $('#page-info');
        
        if (totalPages <= 1) {
            pagination.hide();
            return;
        }
        
        pagination.show();
        prevBtn.prop('disabled', currentPage <= 1);
        nextBtn.prop('disabled', currentPage >= totalPages);
        pageInfo.text(`Page ${currentPage} of ${totalPages}`);
    }
    
    // Event handlers
    $('#refresh-submissions').on('click', function() {
        loadUserSubmissions(currentPage);
    });
    
    $('#prev-page').on('click', function() {
        if (currentPage > 1) {
            loadUserSubmissions(currentPage - 1);
        }
    });
    
    $('#next-page').on('click', function() {
        loadUserSubmissions(currentPage + 1);
    });
    
    // Update connection status when settings change
    $('#surf_social_pusher_key, #surf_social_websocket_url, #surf_social_use_pusher').on('change', function() {
        setTimeout(loadStats, 500);
    });
});
</script>

?>