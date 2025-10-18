<?php
/**
 * Surf Social Nonce Checker
 * 
 * This script helps you verify that all nonces are working correctly
 * and shows you the current nonce values.
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
    wp_die('You do not have permission to access this nonce checker.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Surf Social Nonce Checker</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .nonce-item { margin: 15px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9; }
        .nonce-value { font-family: monospace; background: #f0f0f0; padding: 5px; border-radius: 3px; word-break: break-all; }
        .test-button { background: #007cba; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin: 5px; }
        .test-button:hover { background: #005a87; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { color: #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Surf Social Nonce Checker</h1>
        <p>This tool shows you all the nonces needed for Surf Social and tests their validity.</p>
        
        <div class="nonce-item">
            <h3>1. REST API Nonce (wp_rest)</h3>
            <p><strong>Used for:</strong> All REST API calls to <code>/wp-json/surf-social/v1/</code></p>
            <p><strong>Current nonce:</strong></p>
            <div class="nonce-value"><?php echo wp_create_nonce('wp_rest'); ?></div>
            <button class="test-button" onclick="testRestNonce()">Test REST API</button>
            <div id="rest-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="nonce-item">
            <h3>2. AJAX Stats Nonce (surf_social_stats)</h3>
            <p><strong>Used for:</strong> Admin panel AJAX calls (get support tickets, etc.)</p>
            <p><strong>Current nonce:</strong></p>
            <div class="nonce-value"><?php echo wp_create_nonce('surf_social_stats'); ?></div>
            <button class="test-button" onclick="testAjaxNonce()">Test AJAX</button>
            <div id="ajax-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="nonce-item">
            <h3>3. Debug Nonce (surf_social_debug)</h3>
            <p><strong>Used for:</strong> Debug script AJAX calls</p>
            <p><strong>Current nonce:</strong></p>
            <div class="nonce-value"><?php echo wp_create_nonce('surf_social_debug'); ?></div>
            <button class="test-button" onclick="testDebugNonce()">Test Debug</button>
            <div id="debug-result" style="margin-top: 10px;"></div>
        </div>
        
        <div class="nonce-item">
            <h3>4. JavaScript Configuration</h3>
            <p><strong>Copy this to your JavaScript:</strong></p>
            <div class="nonce-value">
const wpConfig = {
    apiUrl: '<?php echo rest_url('surf-social/v1/'); ?>',
    nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
    ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
    statsNonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
    debugNonce: '<?php echo wp_create_nonce('surf_social_debug'); ?>'
};
            </div>
        </div>
        
        <div class="nonce-item">
            <h3>5. Quick Test All</h3>
            <button class="test-button" onclick="testAll()">Test All Nonces</button>
            <div id="all-results" style="margin-top: 10px;"></div>
        </div>
    </div>

    <script>
    const wpConfig = {
        apiUrl: '<?php echo rest_url('surf-social/v1/'); ?>',
        nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        statsNonce: '<?php echo wp_create_nonce('surf_social_stats'); ?>',
        debugNonce: '<?php echo wp_create_nonce('surf_social_debug'); ?>'
    };
    
    function showResult(elementId, message, type = 'info') {
        const element = document.getElementById(elementId);
        element.innerHTML = `<span class="${type}">${message}</span>`;
    }
    
    async function testRestNonce() {
        showResult('rest-result', 'Testing REST API...', 'info');
        
        try {
            const response = await fetch(wpConfig.apiUrl + 'chat/support?user_id=test', {
                headers: {
                    'X-WP-Nonce': wpConfig.nonce
                }
            });
            
            if (response.ok) {
                showResult('rest-result', '✅ REST API nonce is working!', 'success');
            } else {
                showResult('rest-result', `❌ REST API failed: ${response.status}`, 'error');
            }
        } catch (error) {
            showResult('rest-result', `❌ REST API error: ${error.message}`, 'error');
        }
    }
    
    async function testAjaxNonce() {
        showResult('ajax-result', 'Testing AJAX...', 'info');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_get_support_tickets&nonce=${wpConfig.statsNonce}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                showResult('ajax-result', '✅ AJAX nonce is working!', 'success');
            } else {
                showResult('ajax-result', `❌ AJAX failed: ${data.data || 'Unknown error'}`, 'error');
            }
        } catch (error) {
            showResult('ajax-result', `❌ AJAX error: ${error.message}`, 'error');
        }
    }
    
    async function testDebugNonce() {
        showResult('debug-result', 'Testing Debug...', 'info');
        
        try {
            const response = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=surf_social_debug_config&nonce=${wpConfig.debugNonce}`
            });
            
            const data = await response.json();
            
            if (data.success) {
                showResult('debug-result', '✅ Debug nonce is working!', 'success');
            } else {
                showResult('debug-result', `❌ Debug failed: ${data.data || 'Unknown error'}`, 'error');
            }
        } catch (error) {
            showResult('debug-result', `❌ Debug error: ${error.message}`, 'error');
        }
    }
    
    async function testAll() {
        showResult('all-results', 'Testing all nonces...', 'info');
        
        let results = [];
        
        // Test REST API
        try {
            const restResponse = await fetch(wpConfig.apiUrl + 'chat/support?user_id=test', {
                headers: { 'X-WP-Nonce': wpConfig.nonce }
            });
            results.push(`REST API: ${restResponse.ok ? '✅' : '❌'} (${restResponse.status})`);
        } catch (error) {
            results.push(`REST API: ❌ (${error.message})`);
        }
        
        // Test AJAX
        try {
            const ajaxResponse = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=surf_social_get_support_tickets&nonce=${wpConfig.statsNonce}`
            });
            const ajaxData = await ajaxResponse.json();
            results.push(`AJAX: ${ajaxData.success ? '✅' : '❌'} (${ajaxData.data || 'Unknown error'})`);
        } catch (error) {
            results.push(`AJAX: ❌ (${error.message})`);
        }
        
        // Test Debug
        try {
            const debugResponse = await fetch(wpConfig.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=surf_social_debug_config&nonce=${wpConfig.debugNonce}`
            });
            const debugData = await debugResponse.json();
            results.push(`Debug: ${debugData.success ? '✅' : '❌'} (${debugData.data || 'Unknown error'})`);
        } catch (error) {
            results.push(`Debug: ❌ (${error.message})`);
        }
        
        showResult('all-results', results.join('<br>'), 'info');
    }
    </script>
</body>
</html>
