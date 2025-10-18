<?php
/**
 * Surf Social Support Chat Debug Script
 * 
 * This script helps debug support chat functionality by:
 * 1. Testing database connectivity and table structure
 * 2. Testing REST API endpoints
 * 3. Checking message flow from frontend to admin
 * 4. Verifying real-time functionality
 * 5. Providing detailed logging and error reporting
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress, try to load it
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../../../wp-load.php',
        './wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found. Please place this file in your WordPress installation.');
    }
}

// Only allow admin users
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this debug script.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Surf Social Support Chat Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .debug-container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .debug-section { margin-bottom: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .debug-section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #007cba; padding-bottom: 10px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; font-weight: bold; }
        .code-block { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; margin: 10px 0; font-family: monospace; white-space: pre-wrap; }
        .test-button { background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #005a87; }
        .test-results { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .log-entry { margin: 5px 0; padding: 5px; border-left: 3px solid #007cba; background: #f8f9fa; }
        .log-error { border-left-color: #dc3545; background: #f8d7da; }
        .log-success { border-left-color: #28a745; background: #d4edda; }
        .log-warning { border-left-color: #ffc107; background: #fff3cd; }
    </style>
</head>
<body>
    <div class="debug-container">
        <h1>🔧 Surf Social Support Chat Debug Tool</h1>
        <p>This tool helps diagnose issues with the support chat functionality.</p>
        
        <div class="debug-section">
            <h3>📊 System Information</h3>
            <div class="code-block">
WordPress Version: <?php echo get_bloginfo('version'); ?>
PHP Version: <?php echo PHP_VERSION; ?>
Plugin Version: <?php echo get_option('surf_social_version', 'Unknown'); ?>
Current User: <?php echo wp_get_current_user()->user_login; ?>
Site URL: <?php echo get_site_url(); ?>
Admin URL: <?php echo admin_url(); ?>
            </div>
        </div>

        <div class="debug-section">
            <h3>🗄️ Database Check</h3>
            <button class="test-button" onclick="testDatabase()">Test Database</button>
            <div id="database-results" class="test-results" style="display: none;"></div>
        </div>

        <div class="debug-section">
            <h3>🔌 REST API Check</h3>
            <button class="test-button" onclick="testRestAPI()">Test REST API</button>
            <div id="restapi-results" class="test-results" style="display: none;"></div>
        </div>

        <div class="debug-section">
            <h3>💬 Support Message Flow Test</h3>
            <button class="test-button" onclick="testSupportMessageFlow()">Test Message Flow</button>
            <div id="messageflow-results" class="test-results" style="display: none;"></div>
        </div>

        <div class="debug-section">
            <h3>🔄 Real-time Connection Test</h3>
            <button class="test-button" onclick="testRealtimeConnection()">Test Real-time</button>
            <div id="realtime-results" class="test-results" style="display: none;"></div>
        </div>

        <div class="debug-section">
            <h3>📝 Live Logs</h3>
            <button class="test-button" onclick="startLiveLogging()">Start Live Logging</button>
            <button class="test-button" onclick="stopLiveLogging()">Stop Logging</button>
            <div id="live-logs" class="test-results" style="display: none;"></div>
        </div>

        <div class="debug-section">
            <h3>🛠️ Quick Fixes</h3>
            <button class="test-button" onclick="fixDatabaseTables()">Fix Database Tables</button>
            <button class="test-button" onclick="clearSupportMessages()">Clear Support Messages</button>
            <button class="test-button" onclick="resetPluginSettings()">Reset Plugin Settings</button>
            <div id="fix-results" class="test-results" style="display: none;"></div>
        </div>
    </div>

    <script>
    let liveLoggingInterval = null;
    
    // Get WordPress configuration
    const wpConfig = {
        apiUrl: '<?php echo rest_url('surf-social/v1/'); ?>',
        nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        debugNonce: '<?php echo wp_create_nonce('surf_social_debug'); ?>',
        statsNonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>'
    };

    function log(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry log-${type}`;
        logEntry.innerHTML = `[${timestamp}] ${message}`;
        return logEntry;
    }

    function showResults(containerId, content) {
        const container = document.getElementById(containerId);
        container.innerHTML = content;
        container.style.display = 'block';
    }

    async function testDatabase() {
        showResults('database-results', '<div class="log-entry">Testing database connection...</div>');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_database&nonce=${wpConfig.debugNonce}`
            });
            
            const data = await response.json();
            
            let results = '';
            if (data.success) {
                results += log('✅ Database connection successful', 'success').outerHTML;
                results += log(`Tables found: ${data.data.tables.join(', ')}`, 'info');
                results += log(`Support messages count: ${data.data.support_messages_count}`, 'info');
                results += log(`Guest users count: ${data.data.guest_users_count}`, 'info');
            } else {
                results += log(`❌ Database error: ${data.data}`, 'error');
            }
            
            showResults('database-results', results);
        } catch (error) {
            showResults('database-results', log(`❌ Network error: ${error.message}`, 'error').outerHTML);
        }
    }

    async function testRestAPI() {
        showResults('restapi-results', '<div class="log-entry">Testing REST API endpoints...</div>');
        
        const endpoints = [
            { name: 'Support Messages GET', url: wpConfig.apiUrl + 'chat/support' },
            { name: 'Support Messages POST', url: wpConfig.apiUrl + 'chat/support', method: 'POST' },
            { name: 'Support Tickets GET', url: wpConfig.apiUrl + 'chat/support/admin' },
            { name: 'Pusher Auth POST', url: wpConfig.apiUrl + 'pusher/auth', method: 'POST' }
        ];
        
        let results = '';
        
        for (const endpoint of endpoints) {
            try {
                const options = {
                    method: endpoint.method || 'GET',
                    headers: {
                        'X-WP-Nonce': wpConfig.nonce
                    }
                };
                
                if (endpoint.method === 'POST') {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify({
                        user_id: 'debug_test',
                        user_name: 'Debug Test',
                        message: 'Test message from debug script',
                        message_type: 'user'
                    });
                }
                
                const response = await fetch(endpoint.url, options);
                const data = await response.json();
                
                if (response.ok) {
                    results += log(`✅ ${endpoint.name}: OK (${response.status})`, 'success').outerHTML;
                } else {
                    results += log(`❌ ${endpoint.name}: ${response.status} - ${data.message || 'Unknown error'}`, 'error').outerHTML;
                }
            } catch (error) {
                results += log(`❌ ${endpoint.name}: Network error - ${error.message}`, 'error').outerHTML;
            }
        }
        
        showResults('restapi-results', results);
    }

    async function testSupportMessageFlow() {
        showResults('messageflow-results', '<div class="log-entry">Testing complete message flow...</div>');
        
        let results = '';
        const testUserId = 'debug_test_' + Date.now();
        const testMessage = 'Debug test message ' + new Date().toLocaleTimeString();
        
        try {
            // Step 1: Send support message
            results += log('Step 1: Sending support message...', 'info').outerHTML;
            
            const sendResponse = await fetch(wpConfig.apiUrl + 'chat/support', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpConfig.nonce
                },
                body: JSON.stringify({
                    user_id: testUserId,
                    user_name: 'Debug Test User',
                    message: testMessage,
                    message_type: 'user'
                })
            });
            
            if (sendResponse.ok) {
                results += log('✅ Message sent successfully', 'success').outerHTML;
            } else {
                const errorData = await sendResponse.json();
                results += log(`❌ Failed to send message: ${errorData.message || 'Unknown error'}`, 'error').outerHTML;
                showResults('messageflow-results', results);
                return;
            }
            
            // Step 2: Check if message appears in admin tickets
            results += log('Step 2: Checking admin tickets...', 'info').outerHTML;
            
            const ticketsResponse = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_get_support_tickets&nonce=${wpConfig.statsNonce}`
            });
            
            if (ticketsResponse.ok) {
                const ticketsData = await ticketsResponse.json();
                const foundTicket = ticketsData.data.tickets.find(ticket => ticket.user_id === testUserId);
                
                if (foundTicket) {
                    results += log('✅ Message found in admin tickets', 'success').outerHTML;
                } else {
                    results += log('❌ Message not found in admin tickets', 'error').outerHTML;
                }
            } else {
                results += log('❌ Failed to fetch admin tickets', 'error').outerHTML;
            }
            
            // Step 3: Check if message can be retrieved by user
            results += log('Step 3: Checking user message retrieval...', 'info').outerHTML;
            
            const getResponse = await fetch(`${wpConfig.apiUrl}chat/support?user_id=${testUserId}`, {
                headers: {
                    'X-WP-Nonce': wpConfig.nonce
                }
            });
            
            if (getResponse.ok) {
                const getData = await getResponse.json();
                const foundMessage = getData.messages.find(msg => msg.message === testMessage);
                
                if (foundMessage) {
                    results += log('✅ Message retrieved by user successfully', 'success').outerHTML;
                } else {
                    results += log('❌ Message not found in user retrieval', 'error').outerHTML;
                }
            } else {
                results += log('❌ Failed to retrieve user messages', 'error').outerHTML;
            }
            
        } catch (error) {
            results += log(`❌ Test failed: ${error.message}`, 'error').outerHTML;
        }
        
        showResults('messageflow-results', results);
    }

    async function testRealtimeConnection() {
        showResults('realtime-results', '<div class="log-entry">Testing real-time connection...</div>');
        
        let results = '';
        
        // Check Pusher configuration
        results += log('Checking Pusher configuration...', 'info').outerHTML;
        
        try {
            const configResponse = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_config&nonce=${wpConfig.debugNonce}`
            });
            
            const configData = await configResponse.json();
            
            if (configData.success) {
                results += log(`Pusher Key: ${configData.data.pusher_key}`, 'info').outerHTML;
                results += log(`Pusher Cluster: ${configData.data.pusher_cluster}`, 'info').outerHTML;
                results += log(`Use Pusher: ${configData.data.use_pusher}`, 'info').outerHTML;
                results += log(`WebSocket URL: ${configData.data.websocket_url || 'Not set'}`, 'info').outerHTML;
                
                if (configData.data.use_pusher && configData.data.pusher_key) {
                    results += log('✅ Pusher configuration looks good', 'success').outerHTML;
                } else {
                    results += log('⚠️ Pusher not properly configured', 'warning').outerHTML;
                }
            } else {
                results += log('❌ Failed to get configuration', 'error').outerHTML;
            }
        } catch (error) {
            results += log(`❌ Configuration check failed: ${error.message}`, 'error').outerHTML;
        }
        
        showResults('realtime-results', results);
    }

    function startLiveLogging() {
        if (liveLoggingInterval) {
            clearInterval(liveLoggingInterval);
        }
        
        showResults('live-logs', '<div class="log-entry">Starting live logging...</div>');
        
        liveLoggingInterval = setInterval(async () => {
            try {
                const response = await fetch(wpConfig.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=surf_social_debug_live_logs&nonce=${wpConfig.debugNonce}`
                });
                
                const data = await response.json();
                if (data.success && data.data.logs.length > 0) {
                    const logsContainer = document.getElementById('live-logs');
                    data.data.logs.forEach(log => {
                        const logEntry = document.createElement('div');
                        logEntry.className = `log-entry log-${log.type}`;
                        logEntry.innerHTML = `[${log.timestamp}] ${log.message}`;
                        logsContainer.appendChild(logEntry);
                    });
                    logsContainer.scrollTop = logsContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Live logging error:', error);
            }
        }, 2000);
    }

    function stopLiveLogging() {
        if (liveLoggingInterval) {
            clearInterval(liveLoggingInterval);
            liveLoggingInterval = null;
        }
        
        const logsContainer = document.getElementById('live-logs');
        const stopEntry = document.createElement('div');
        stopEntry.className = 'log-entry log-warning';
        stopEntry.innerHTML = '[Stopped] Live logging stopped';
        logsContainer.appendChild(stopEntry);
    }

    async function fixDatabaseTables() {
        showResults('fix-results', '<div class="log-entry">Fixing database tables...</div>');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_fix_tables&nonce=${wpConfig.debugNonce}`
            });
            
            const data = await response.json();
            
            let results = '';
            if (data.success) {
                results += log('✅ Database tables fixed successfully', 'success').outerHTML;
                results += log(data.data.message, 'info').outerHTML;
            } else {
                results += log(`❌ Failed to fix tables: ${data.data}`, 'error').outerHTML;
            }
            
            showResults('fix-results', results);
        } catch (error) {
            showResults('fix-results', log(`❌ Fix failed: ${error.message}`, 'error').outerHTML);
        }
    }

    async function clearSupportMessages() {
        if (!confirm('Are you sure you want to clear all support messages? This cannot be undone.')) {
            return;
        }
        
        showResults('fix-results', '<div class="log-entry">Clearing support messages...</div>');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_clear_messages&nonce=${wpConfig.debugNonce}`
            });
            
            const data = await response.json();
            
            let results = '';
            if (data.success) {
                results += log('✅ Support messages cleared successfully', 'success').outerHTML;
                results += log(`Cleared ${data.data.count} messages`, 'info').outerHTML;
            } else {
                results += log(`❌ Failed to clear messages: ${data.data}`, 'error').outerHTML;
            }
            
            showResults('fix-results', results);
        } catch (error) {
            showResults('fix-results', log(`❌ Clear failed: ${error.message}`, 'error').outerHTML);
        }
    }

    async function resetPluginSettings() {
        if (!confirm('Are you sure you want to reset plugin settings? This will restore default values.')) {
            return;
        }
        
        showResults('fix-results', '<div class="log-entry">Resetting plugin settings...</div>');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_reset_settings&nonce=${wpConfig.debugNonce}`
            });
            
            const data = await response.json();
            
            let results = '';
            if (data.success) {
                results += log('✅ Plugin settings reset successfully', 'success').outerHTML;
            } else {
                results += log(`❌ Failed to reset settings: ${data.data}`, 'error').outerHTML;
            }
            
            showResults('fix-results', results);
        } catch (error) {
            showResults('fix-results', log(`❌ Reset failed: ${error.message}`, 'error').outerHTML);
        }
    }
    </script>
</body>
</html>
