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
    background: white;
    color: #1d2327;
    padding: 20px;
    border-radius: 1px;
    border: 1px solid #000000;
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

/* Message Management Table Styles */
.surf-message-management-container {
    margin-top: 20px;
}

.surf-message-management-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.surf-message-management-table th {
    background: #f8f9fa;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    color: #1d2327;
    border-bottom: 2px solid #e1e5e9;
    white-space: nowrap;
}

.surf-message-management-table td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f1;
    vertical-align: top;
}

.surf-message-management-table tbody tr:hover {
    background: #f8f9fa;
}

.surf-message-management-table tbody tr:last-child td {
    border-bottom: none;
}

.surf-message-content {
    max-width: 300px;
    word-wrap: break-word;
    line-height: 1.4;
}

.surf-message-actions {
    white-space: nowrap;
}

.surf-delete-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: background-color 0.2s;
}

.surf-delete-btn:hover {
    background: #c82333;
}

.surf-delete-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
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

.surf-sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
    transition: background-color 0.2s;
}

.surf-sortable:hover {
    background: #e9ecef;
}

.surf-sort-indicator {
    margin-left: 8px;
    font-size: 12px;
    opacity: 0.6;
    transition: opacity 0.2s;
}

.surf-sortable.surf-sort-asc .surf-sort-indicator::after {
    content: " ↑";
    opacity: 1;
}

.surf-sortable.surf-sort-desc .surf-sort-indicator::after {
    content: " ↓";
    opacity: 1;
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

/* Support Management Styles */
.surf-support-interface {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    min-height: 600px;
    margin-top: 20px;
}

.surf-tickets-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    display: flex;
    flex-direction: column;
}

.surf-tickets-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e1e5e9;
}

.surf-tickets-header h3 {
    margin: 0;
    color: #1d2327;
    font-size: 16px;
}

.surf-tickets-controls {
    display: flex;
    gap: 10px;
    align-items: center;
}

.surf-tickets-controls select {
    padding: 6px 10px;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    background: white;
    font-size: 14px;
}

.surf-tickets-list {
    flex: 1;
    overflow-y: auto;
    max-height: 500px;
}

.surf-ticket-item {
    background: white;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
}

.surf-ticket-item:hover {
    border-color: #007cba;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.surf-ticket-item.active {
    border-color: #007cba;
    background: #f0f8ff;
}

.surf-ticket-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.surf-ticket-user {
    font-weight: 600;
    color: #1d2327;
    font-size: 14px;
}

.surf-ticket-status {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.surf-ticket-status.unread {
    background: #fff3cd;
    color: #856404;
}

.surf-ticket-status.read {
    background: #d1ecf1;
    color: #0c5460;
}

.surf-ticket-message {
    color: #666;
    font-size: 13px;
    line-height: 1.4;
    margin-bottom: 6px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.surf-ticket-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: #999;
}

.surf-ticket-time {
    font-size: 11px;
}

.surf-ticket-count {
    background: #007cba;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
}

.surf-conversation-panel {
    background: white;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    min-height: 600px;
}

.surf-conversation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e1e5e9;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.surf-conversation-header h3 {
    margin: 0;
    color: #1d2327;
    font-size: 16px;
}

.surf-conversation-actions {
    display: flex;
    gap: 10px;
}


.surf-conversation-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    max-height: 400px;
}

.surf-empty-conversation {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
    text-align: center;
}

.surf-empty-conversation svg {
    width: 48px;
    height: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.surf-empty-conversation p {
    margin: 0;
    font-size: 14px;
}

.surf-message-item {
    margin-bottom: 16px;
    display: flex;
    flex-direction: column;
}

.surf-message-item.admin {
    align-items: flex-end;
}

.surf-message-item.user {
    align-items: flex-start;
}

.surf-message-bubble {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.4;
    word-wrap: break-word;
}

.surf-message-item.admin .surf-message-bubble {
    background: #007cba;
    color: white;
    border-bottom-right-radius: 4px;
}

.surf-message-item.user .surf-message-bubble {
    background: #f1f3f4;
    color: #1d2327;
    border-bottom-left-radius: 4px;
}

.surf-message-meta {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
    padding: 0 8px;
}

.surf-conversation-input {
    padding: 15px 20px;
    border-top: 1px solid #e1e5e9;
    background: #f8f9fa;
    border-radius: 0 0 8px 8px;
}

.surf-conversation-input textarea {
    width: 100%;
    border: 1px solid #e1e5e9;
    border-radius: 6px;
    padding: 10px 12px;
    font-size: 14px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
}

.surf-conversation-input textarea:focus {
    outline: none;
    border-color: #007cba;
    box-shadow: 0 0 0 2px rgba(0, 124, 186, 0.1);
}

.surf-conversation-input-actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}

.surf-send-reply-btn {
    background: #007cba;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.2s;
}

.surf-send-reply-btn:hover {
    background: #005a87;
}

.surf-send-reply-btn:disabled {
    background: #6c757d;
    cursor: not-allowed;
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
        <h2>💬 Message Management</h2>
        
        <div class="surf-message-management-container">
            <div class="surf-table-header">
                <div class="surf-table-controls">
                    <button class="surf-refresh-btn" id="refresh-messages">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path d="M1 4V10H7M23 20V14H17M20.49 9A9 9 0 0 0 5.64 5.64L1 10M23 14L18.36 18.36A9 9 0 0 1 3.51 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>
            
            <div class="surf-table-wrapper">
                <table class="surf-message-management-table" id="surf-message-management-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Message</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="surf-messages-tbody">
                        <tr>
                            <td colspan="4" class="surf-loading">Loading messages...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="surf-table-pagination" id="surf-messages-pagination" style="display: none;">
                <button class="surf-pagination-btn" id="prev-message-page" disabled>Previous</button>
                <span class="surf-page-info" id="message-page-info">Page 1 of 1</span>
                <button class="surf-pagination-btn" id="next-message-page" disabled>Next</button>
            </div>
        </div>
    </div>
    
    <div class="surf-settings-section">
        <h2>🆘 Support Management</h2>
        
        <div class="surf-support-interface">
            <!-- Left Panel: Tickets List -->
            <div class="surf-tickets-panel">
                <div class="surf-tickets-header">
                    <h3>Support Tickets</h3>
                    <div class="surf-tickets-controls">
                        <select id="support-status-filter">
                            <option value="all">All Tickets</option>
                            <option value="unread">Unread</option>
                            <option value="read">Read</option>
                        </select>
                        <button class="surf-refresh-btn" id="refresh-support-tickets">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path d="M1 4V10H7M23 20V14H17M20.49 9A9 9 0 0 0 5.64 5.64L1 10M23 14L18.36 18.36A9 9 0 0 1 3.51 15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Refresh
                        </button>
                    </div>
                </div>
                
                <div class="surf-tickets-list" id="surf-tickets-list">
                    <div class="surf-loading">Loading support tickets...</div>
                </div>
            </div>
            
            <!-- Right Panel: Conversation View -->
            <div class="surf-conversation-panel">
                <div class="surf-conversation-header">
                    <h3 id="conversation-title">Select a ticket to view conversation</h3>
                    <div class="surf-conversation-actions">
                    </div>
                </div>
                
                <div class="surf-conversation-messages" id="surf-conversation-messages">
                    <div class="surf-empty-conversation">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M21 15C21 15.5304 20.7893 16.0391 20.4142 16.4142C20.0391 16.7893 19.5304 17 19 17H7L3 21V5C3 4.46957 3.21071 3.96086 3.58579 3.58579C3.96086 3.21071 4.46957 3 5 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V15Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <p>Select a support ticket to view the conversation</p>
                    </div>
                </div>
                
                <div class="surf-conversation-input" id="surf-conversation-input" style="display: none;">
                    <textarea id="admin-reply-input" placeholder="Type your response..." rows="3"></textarea>
                    <div class="surf-conversation-input-actions">
                        <button id="send-reply-btn" class="surf-send-reply-btn">Send Reply</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="surf-settings-section">
        <h2>👥 User Submissions</h2>
        
        <div class="surf-user-submissions-table-container">
            <div class="surf-table-header">
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
                            <th class="surf-sortable" data-sort="name">
                                Name
                                <span class="surf-sort-indicator">↕</span>
                            </th>
                            <th class="surf-sortable" data-sort="email">
                                Email
                                <span class="surf-sort-indicator">↕</span>
                            </th>
                            <th class="surf-sortable" data-sort="date">
                                Date Entered
                                <span class="surf-sort-indicator">↕</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="surf-submissions-tbody">
                        <tr>
                            <td colspan="3" class="surf-loading">Loading user submissions...</td>
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
    let currentSort = 'date'; // Default sort by date (most recent first)
    let sortDirection = 'desc'; // Default direction
    
    // Message management variables
    let currentMessagePage = 1;
    const messagePerPage = 10;
    
    // Support management variables
    let currentSupportTickets = [];
    let selectedTicketUserId = null;
    let supportStatusFilter = 'all';
    
    // Load stats, user submissions, and messages on page load
    loadStats();
    loadUserSubmissions();
    loadMessages();
    loadSupportTickets();
    
    
    // Initialize default sort indicator
    $(`.surf-sortable[data-sort="${currentSort}"]`).addClass(`surf-sort-${sortDirection}`);
    
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
                sort: currentSort,
                direction: sortDirection,
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayUserSubmissions(response.data);
                } else {
                    $('#surf-submissions-tbody').html('<tr><td colspan="3" class="surf-loading">Error loading user submissions</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('User Submissions AJAX Error:', xhr.responseText);
                $('#surf-submissions-tbody').html('<tr><td colspan="3" class="surf-loading">Error loading user submissions</td></tr>');
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
                
                html += `
                    <tr>
                        <td><strong>${submission.name}</strong></td>
                        <td>${submission.email}</td>
                        <td>${createdDate}</td>
                    </tr>
                `;
            });
            tbody.html(html);
            
            // Update pagination
            updatePagination(data.total_pages, data.current_page);
        } else {
            tbody.html('<tr><td colspan="3" class="surf-loading">No user submissions found</td></tr>');
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
    
    // Sorting functionality
    function handleSort(column) {
        if (currentSort === column) {
            // Toggle direction if same column
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // Set new column and default direction
            currentSort = column;
            sortDirection = 'asc';
        }
        
        // Update visual indicators
        $('.surf-sortable').removeClass('surf-sort-asc surf-sort-desc');
        $(`.surf-sortable[data-sort="${column}"]`).addClass(`surf-sort-${sortDirection}`);
        
        // Reload data with new sorting
        loadUserSubmissions(1);
    }
    
    // Message management functions
    function loadMessages(page = 1) {
        currentMessagePage = page;
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'surf_social_get_messages',
                page: page,
                per_page: messagePerPage,
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayMessages(response.data);
                } else {
                    $('#surf-messages-tbody').html('<tr><td colspan="4" class="surf-loading">Error loading messages</td></tr>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Messages AJAX Error:', xhr.responseText);
                $('#surf-messages-tbody').html('<tr><td colspan="4" class="surf-loading">Error loading messages</td></tr>');
            }
        });
    }
    
    function displayMessages(data) {
        const tbody = $('#surf-messages-tbody');
        
        if (data.messages && data.messages.length > 0) {
            let html = '';
            data.messages.forEach(function(message) {
                const messageDate = new Date(message.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                // Truncate long messages
                const truncatedMessage = message.message.length > 100 
                    ? message.message.substring(0, 100) + '...' 
                    : message.message;
                
                html += `
                    <tr data-message-id="${message.id}">
                        <td><strong>${message.user_name}</strong></td>
                        <td class="surf-message-content">${truncatedMessage}</td>
                        <td>${messageDate}</td>
                        <td class="surf-message-actions">
                            <button class="surf-delete-btn" onclick="deleteMessage(${message.id})">
                                Delete
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
            
            // Update pagination
            updateMessagePagination(data.total_pages, data.current_page);
        } else {
            tbody.html('<tr><td colspan="4" class="surf-loading">No messages found</td></tr>');
            $('#surf-messages-pagination').hide();
        }
    }
    
    function updateMessagePagination(totalPages, currentPage) {
        const pagination = $('#surf-messages-pagination');
        const prevBtn = $('#prev-message-page');
        const nextBtn = $('#next-message-page');
        const pageInfo = $('#message-page-info');
        
        if (totalPages <= 1) {
            pagination.hide();
            return;
        }
        
        pagination.show();
        prevBtn.prop('disabled', currentPage <= 1);
        nextBtn.prop('disabled', currentPage >= totalPages);
        pageInfo.text(`Page ${currentPage} of ${totalPages}`);
    }
    
    // Global function for message deletion
    window.deleteMessage = function(messageId) {
        if (!confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
            return;
        }
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'surf_social_delete_message',
                message_id: messageId,
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Remove the message row from the table
                    $(`tr[data-message-id="${messageId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    // Reload messages to update pagination
                    loadMessages(currentMessagePage);
                } else {
                    alert('Error deleting message: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete Message Error:', xhr.responseText);
                alert('Error deleting message. Please try again.');
            }
        });
    };
    
    // Event handlers
    $('.surf-sortable').on('click', function() {
        const column = $(this).data('sort');
        handleSort(column);
    });
    
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
    
    // Message management event handlers
    $('#refresh-messages').on('click', function() {
        loadMessages(currentMessagePage);
    });
    
    $('#prev-message-page').on('click', function() {
        if (currentMessagePage > 1) {
            loadMessages(currentMessagePage - 1);
        }
    });
    
    $('#next-message-page').on('click', function() {
        loadMessages(currentMessagePage + 1);
    });
    
    // Support management functions
    function loadSupportTickets() {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'surf_social_get_support_tickets',
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
                status: supportStatusFilter
            },
            success: function(response) {
                if (response.success) {
                    currentSupportTickets = response.data.tickets;
                    displaySupportTickets();
                } else {
                    console.error('Error loading support tickets:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error loading support tickets:', error, xhr.responseText);
            }
        });
    }
    
    function displaySupportTickets() {
        const ticketsList = $('#surf-tickets-list');
        ticketsList.empty();
        
        if (currentSupportTickets.length === 0) {
            ticketsList.html('<div class="surf-loading">No support tickets found</div>');
            return;
        }
        
        currentSupportTickets.forEach(ticket => {
            const ticketEl = $(`
                <div class="surf-ticket-item" data-user-id="${ticket.user_id}">
                    <div class="surf-ticket-header">
                        <div class="surf-ticket-user">${ticket.user_name}</div>
                        <div class="surf-ticket-status ${ticket.is_read_by_admin ? 'read' : 'unread'}">${ticket.is_read_by_admin ? 'Read' : 'Unread'}</div>
                    </div>
                    <div class="surf-ticket-message">${ticket.last_message || 'No messages yet'}</div>
                    <div class="surf-ticket-meta">
                        <div class="surf-ticket-time">${formatTimeAgo(ticket.last_message_time)}</div>
                        <div class="surf-ticket-count">${ticket.message_count}</div>
                    </div>
                </div>
            `);
            
            ticketsList.append(ticketEl);
        });
        
        // Add click handlers
        $('.surf-ticket-item').on('click', function() {
            const userId = $(this).data('user-id');
            selectSupportTicket(userId);
        });
    }
    
    function selectSupportTicket(userId) {
        selectedTicketUserId = userId;
        
        // Update UI
        $('.surf-ticket-item').removeClass('active');
        $(`.surf-ticket-item[data-user-id="${userId}"]`).addClass('active');
        
        // Load conversation
        loadSupportConversation(userId);
        
        // Mark messages as read
        markSupportAsRead(userId);
    }
    
    function loadSupportConversation(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'GET',
            data: {
                action: 'surf_social_get_support_conversation',
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    displaySupportConversation(response.data.messages, response.data.user_info);
                } else {
                    $('#surf-conversation-messages').html('<div class="surf-error">Error loading conversation</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#surf-conversation-messages').html('<div class="surf-error">Error loading conversation</div>');
            }
        });
    }
    
    function displaySupportConversation(messages, userInfo) {
        const conversationMessages = $('#surf-conversation-messages');
        const conversationTitle = $('#conversation-title');
        const conversationInput = $('#surf-conversation-input');
        // Update title
        conversationTitle.text(`Chat with ${userInfo.user_name}`);
        
        // Always show input for support conversations
        conversationInput.show();
        
        // Clear and populate messages
        conversationMessages.empty();
        
        if (messages.length === 0) {
            conversationMessages.html('<div class="surf-loading">No messages in this conversation</div>');
            return;
        }
        
        messages.forEach(message => {
            const messageEl = $(`
                <div class="surf-message-item ${message.message_type}">
                    <div class="surf-message-bubble">${message.message}</div>
                    <div class="surf-message-meta">
                        ${message.message_type === 'admin' ? 'Admin' : message.user_name} • ${formatTimeAgo(message.created_at)}
                    </div>
                </div>
            `);
            conversationMessages.append(messageEl);
        });
        
        // Scroll to bottom
        conversationMessages.scrollTop(conversationMessages[0].scrollHeight);
    }
    
    function sendAdminReply() {
        const message = $('#admin-reply-input').val().trim();
        if (!message || !selectedTicketUserId) return;
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'surf_social_send_admin_reply',
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
                user_id: selectedTicketUserId,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $('#admin-reply-input').val('');
                    // Reload conversation to show new message
                    loadSupportConversation(selectedTicketUserId);
                    // Reload tickets list to update counts
                    loadSupportTickets();
                } else {
                    alert('Error sending reply: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                alert('Error sending reply: ' + error);
            }
        });
    }
    
    function markSupportAsRead(userId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'surf_social_mark_support_read',
                nonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    // Update the ticket status in the UI
                    $(`.surf-ticket-item[data-user-id="${userId}"] .surf-ticket-status`)
                        .removeClass('unread')
                        .addClass('read')
                        .text('Read');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error marking support as read:', error);
            }
        });
    }
    
    function formatTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    
    // Support management event handlers
    $('#refresh-support-tickets').on('click', function() {
        loadSupportTickets();
    });
    
    $('#support-status-filter').on('change', function() {
        supportStatusFilter = $(this).val();
        loadSupportTickets();
    });
    
    $('#send-reply-btn').on('click', function() {
        sendAdminReply();
    });
    
    $('#admin-reply-input').on('keypress', function(e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendAdminReply();
        }
    });
    
    
    
    
    
    // Update connection status when settings change
    $('#surf_social_pusher_key, #surf_social_websocket_url, #surf_social_use_pusher').on('change', function() {
        setTimeout(loadStats, 500);
    });
});
</script>

?>