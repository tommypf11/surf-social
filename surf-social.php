<?php
/*
Plugin Name: Surf Social
Plugin URI: https://github.com/tommypf11/surf-social
GitHub Plugin URI: https://github.com/tommypf11/surf-social
Description: Your plugin description
Version: 1.0.74
Author: Thomas Fraher
*/


// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SURF_SOCIAL_VERSION', '1.0.0');
define('SURF_SOCIAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SURF_SOCIAL_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Surf Social Plugin Class
 */
class Surf_Social {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 20);
        add_action('wp_footer', array($this, 'enqueue_footer_styles'), 1); // Load styles in footer with high priority
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'add_cors_headers'));
        add_action('wp_ajax_surf_social_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_surf_social_get_user_submissions', array($this, 'ajax_get_user_submissions'));
        add_action('wp_ajax_surf_social_get_messages', array($this, 'ajax_get_messages'));
        add_action('wp_ajax_surf_social_delete_message', array($this, 'ajax_delete_message'));
        add_action('wp_ajax_surf_social_get_support_tickets', array($this, 'ajax_get_support_tickets'));
        add_action('wp_ajax_surf_social_get_support_conversation', array($this, 'ajax_get_support_conversation'));
        add_action('wp_ajax_surf_social_send_admin_reply', array($this, 'ajax_send_admin_reply'));
        add_action('wp_ajax_surf_social_mark_support_read', array($this, 'ajax_mark_support_read'));
        
        // Debug AJAX handlers
        add_action('wp_ajax_surf_social_debug_database', array($this, 'ajax_debug_database'));
        add_action('wp_ajax_surf_social_debug_config', array($this, 'ajax_debug_config'));
        add_action('wp_ajax_surf_social_debug_live_logs', array($this, 'ajax_debug_live_logs'));
        add_action('wp_ajax_surf_social_debug_fix_tables', array($this, 'ajax_debug_fix_tables'));
        add_action('wp_ajax_surf_social_debug_clear_messages', array($this, 'ajax_debug_clear_messages'));
        add_action('wp_ajax_surf_social_debug_reset_settings', array($this, 'ajax_debug_reset_settings'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('surf-social', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Ensure database tables exist
        $this->ensure_database_tables();
    }
    
    /**
     * Ensure database tables exist
     */
    private function ensure_database_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check if guests table exists
        $guests_table = $wpdb->prefix . 'surf_social_guests';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$guests_table'");
        
        if (!$table_exists) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $sql = "CREATE TABLE IF NOT EXISTS $guests_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id varchar(100) NOT NULL,
                name varchar(100) NOT NULL,
                email varchar(255) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY user_id (user_id),
                KEY email (email),
                KEY created_at (created_at)
            ) $charset_collate;";
            dbDelta($sql);
        }
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }
        
        // Enqueue styles with high priority to override theme styles
        wp_enqueue_style(
            'surf-social-style',
            SURF_SOCIAL_PLUGIN_URL . 'assets/css/surf-social.css',
            array(), // No dependencies - load after theme
            SURF_SOCIAL_VERSION,
            'all'
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'surf-social-script',
            SURF_SOCIAL_PLUGIN_URL . 'assets/js/surf-social.js',
            array('jquery'),
            SURF_SOCIAL_VERSION . '.' . time(), // Force cache refresh
            true
        );
        
        // Localize script with settings
        $current_user = wp_get_current_user();
        $user_id = get_current_user_id();
        
        // Generate guest user info if not logged in
        if (!$user_id) {
            $user_id = 'guest_' . wp_generate_password(8, false);
            $user_name = 'Guest ' . substr($user_id, -4);
            $is_guest = true;
        } else {
            $user_name = $current_user->display_name ?: $current_user->user_login;
            $is_guest = false;
        }
        
        // Ensure we always have a user ID and name
        if (empty($user_id)) {
            $user_id = 'guest_' . uniqid();
            $user_name = 'Guest User';
            $is_guest = true;
        }
        if (empty($user_name)) {
            $user_name = 'Guest User';
        }
        if (!isset($is_guest)) {
            $is_guest = false;
        }
        
        // Debug logging
        error_log('Surf Social User Debug:');
        error_log('- Original user_id: ' . get_current_user_id());
        error_log('- Generated user_id: ' . $user_id);
        error_log('- Generated user_name: ' . $user_name);
        error_log('- Is logged in: ' . (get_current_user_id() ? 'yes' : 'no'));
        
        // Generate avatar URL safely
        $avatar_url = '';
        if (get_current_user_id()) {
            $avatar_url = get_avatar_url(get_current_user_id());
        } else {
            // Use a default avatar for guest users
            $avatar_url = 'https://via.placeholder.com/40/FF6B6B/FFFFFF?text=' . urlencode(substr($user_name, 0, 1));
        }
        
        wp_localize_script('surf-social-script', 'surfSocial', array(
            'pusherKey' => get_option('surf_social_pusher_key', 'c08d09b4013a00d6a626'),
            'pusherCluster' => get_option('surf_social_pusher_cluster', 'us3'),
            'usePusher' => get_option('surf_social_use_pusher', '1') === '1',
            'websocketUrl' => get_option('surf_social_websocket_url', ''),
            'currentUser' => array(
                'id' => $user_id,
                'name' => $user_name,
                'email' => '', // Will be set by guest registration
                'avatar' => $avatar_url,
                'color' => $this->get_user_color($user_id),
                'isGuest' => $is_guest
            ),
            'apiUrl' => rest_url('surf-social/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Enqueue footer styles to ensure they load after theme styles
     */
    public function enqueue_footer_styles() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }
        
        // Re-enqueue styles in footer with higher priority
        wp_enqueue_style(
            'surf-social-footer-style',
            SURF_SOCIAL_PLUGIN_URL . 'assets/css/surf-social.css',
            array(), // No dependencies
            SURF_SOCIAL_VERSION . '.' . time(), // Force cache refresh
            'all'
        );
    }
    
    /**
     * Get user color based on user ID
     */
    private function get_user_color($user_id) {
        $colors = array(
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
            '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B739', '#52B788'
        );
        
        // Handle guest users
        if (is_string($user_id) && strpos($user_id, 'guest_') === 0) {
            $hash = crc32($user_id);
            return $colors[abs($hash) % count($colors)];
        }
        
        // Handle numeric user IDs
        return $colors[abs($user_id) % count($colors)];
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Surf Social Settings', 'surf-social'),
            __('Surf Social', 'surf-social'),
            'manage_options',
            'surf-social',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Set default values if not already set
        if (!get_option('surf_social_pusher_key')) {
            update_option('surf_social_pusher_key', 'c08d09b4013a00d6a626');
        }
        if (!get_option('surf_social_pusher_secret')) {
            update_option('surf_social_pusher_secret', '15a73a9dbb0a4884f6fa');
        }
        if (!get_option('surf_social_pusher_cluster')) {
            update_option('surf_social_pusher_cluster', 'us3');
        }
        if (!get_option('surf_social_use_pusher')) {
            update_option('surf_social_use_pusher', '1');
        }
        if (!get_option('surf_social_enabled')) {
            update_option('surf_social_enabled', '1');
        }
        
        register_setting('surf_social_settings', 'surf_social_pusher_key');
        register_setting('surf_social_settings', 'surf_social_pusher_secret');
        register_setting('surf_social_settings', 'surf_social_pusher_cluster');
        register_setting('surf_social_settings', 'surf_social_use_pusher');
        register_setting('surf_social_settings', 'surf_social_websocket_url');
        register_setting('surf_social_settings', 'surf_social_enabled');
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        include SURF_SOCIAL_PLUGIN_DIR . 'includes/admin-page.php';
    }
    
    /**
     * Render chat widget in footer
     */
    public function render_chat_widget() {
        if (get_option('surf_social_enabled', '1') !== '1') {
            return;
        }
        include SURF_SOCIAL_PLUGIN_DIR . 'includes/chat-widget.php';
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // Web chat endpoints
        register_rest_route('surf-social/v1', '/chat/messages', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_chat_messages'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/messages', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_chat_message'),
            'permission_callback' => '__return_true'
        ));
        
        // Individual chat endpoints
        register_rest_route('surf-social/v1', '/chat/individual', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_individual_messages'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/individual', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_individual_message'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/individual/conversations', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_individual_conversations'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/individual/load-conversations', array(
            'methods' => 'GET',
            'callback' => array($this, 'load_user_conversations'),
            'permission_callback' => '__return_true'
        ));
        
        // Support chat endpoints
        register_rest_route('surf-social/v1', '/chat/support', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_support_messages'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/support', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_support_message'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('surf-social/v1', '/chat/support/admin', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_support_tickets'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Pusher authentication endpoint
        register_rest_route('surf-social/v1', '/pusher/auth', array(
            'methods' => 'POST',
            'callback' => array($this, 'pusher_auth'),
            'permission_callback' => array($this, 'pusher_auth_permission')
        ));
        
        // Stats endpoint for admin
        register_rest_route('surf-social/v1', '/stats', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_stats'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));
        
        // Guest registration endpoint
        register_rest_route('surf-social/v1', '/guest/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_guest'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Get chat messages
     */
    public function get_chat_messages($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_messages';
        
        $page = $request->get_param('page') ?: 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        return new WP_REST_Response(array(
            'messages' => array_reverse($messages),
            'page' => $page,
            'has_more' => count($messages) === $per_page
        ), 200);
    }
    
    /**
     * Save chat message
     */
    public function save_chat_message($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_messages';
        
        $message = $request->get_param('message');
        $user_name = $request->get_param('user_name');
        $user_id = $request->get_param('user_id');
        $channel = $request->get_param('channel') ?: 'web';
        
        if (empty($message) || empty($user_name)) {
            return new WP_Error('invalid_data', 'Message and user name are required', array('status' => 400));
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'user_name' => sanitize_text_field($user_name),
                'message' => sanitize_textarea_field($message),
                'channel' => sanitize_text_field($channel),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $message_id = $wpdb->insert_id;
            return new WP_REST_Response(array(
                'success' => true,
                'message_id' => $message_id
            ), 201);
        }
        
        return new WP_Error('save_failed', 'Failed to save message', array('status' => 500));
    }
    
    /**
     * Permission callback for Pusher authentication
     */
    public function pusher_auth_permission($request) {
        // Allow authentication for all requests (for guest users)
        // In production, you might want to add additional security checks
        return true;
    }
    
    /**
     * Add CORS headers for Pusher authentication
     */
    public function add_cors_headers() {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
        }
        
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
            }
            
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            
            exit(0);
        }
    }
    
    /**
     * Pusher authentication for private channels
     */
    public function pusher_auth($request) {
        // Debug logging
        error_log('Pusher auth called with params: ' . print_r($request->get_params(), true));
        error_log('Request headers: ' . print_r($request->get_headers(), true));
        
        // Set proper headers
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        
        $socket_id = $request->get_param('socket_id');
        $channel_name = $request->get_param('channel_name');
        
        if (empty($socket_id) || empty($channel_name)) {
            error_log('Missing socket_id or channel_name');
            return new WP_Error('missing_params', 'Socket ID and channel name are required', array('status' => 400));
        }
        
        $pusher_secret = get_option('surf_social_pusher_secret', '');
        if (empty($pusher_secret)) {
            error_log('Pusher secret not configured');
            return new WP_Error('no_secret', 'Pusher secret not configured', array('status' => 500));
        }
        
        // For private channels, we just need to authenticate the channel subscription
        $string_to_sign = $socket_id . ':' . $channel_name;
        $auth_signature = hash_hmac('sha256', $string_to_sign, $pusher_secret);
        
        $auth_string = get_option('surf_social_pusher_key', '') . ':' . $auth_signature;
        
        error_log('Generated auth string: ' . $auth_string);
        
        $response = new WP_REST_Response(array(
            'auth' => $auth_string
        ), 200);
        
        // Add headers to response
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Content-Type', 'application/json');
        
        return $response;
    }
    
    /**
     * Get individual chat messages
     */
    public function get_individual_messages($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        
        $user_id = $request->get_param('user_id');
        $target_user_id = $request->get_param('target_user_id');
        $page = $request->get_param('page') ?: 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Debug logging
        error_log('Surf Social Debug - get_individual_messages called');
        error_log('Surf Social Debug - user_id: ' . $user_id);
        error_log('Surf Social Debug - target_user_id: ' . $target_user_id);
        
        if (empty($user_id) || empty($target_user_id)) {
            error_log('Surf Social Debug - Missing required parameters');
            return new WP_Error('missing_params', 'User ID and target user ID are required', array('status' => 400));
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log('Surf Social Debug - Table does not exist: ' . $table_name);
            return new WP_Error('table_not_found', 'Individual messages table not found', array('status' => 500));
        }
        
        // Handle both string and integer user IDs
        $user_id_escaped = $wpdb->esc_like($user_id);
        $target_user_id_escaped = $wpdb->esc_like($target_user_id);
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE (sender_id = %s AND recipient_id = %s) 
                OR (sender_id = %s AND recipient_id = %s)
                ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $user_id, $target_user_id, $target_user_id, $user_id, $per_page, $offset
            ),
            ARRAY_A
        );
        
        error_log('Surf Social Debug - Found ' . count($messages) . ' messages');
        if (count($messages) > 0) {
            error_log('Surf Social Debug - First message: ' . print_r($messages[0], true));
        }
        
        return new WP_REST_Response(array(
            'messages' => array_reverse($messages),
            'page' => $page,
            'has_more' => count($messages) === $per_page
        ), 200);
    }
    
    /**
     * Save individual chat message
     */
    public function save_individual_message($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        
        $sender_id = $request->get_param('sender_id');
        $sender_name = $request->get_param('sender_name');
        $recipient_id = $request->get_param('recipient_id');
        $recipient_name = $request->get_param('recipient_name');
        $message = $request->get_param('message');
        
        // Debug logging
        error_log('Surf Social Debug - save_individual_message called');
        error_log('Surf Social Debug - sender_id: ' . $sender_id);
        error_log('Surf Social Debug - sender_name: ' . $sender_name);
        error_log('Surf Social Debug - recipient_id: ' . $recipient_id);
        error_log('Surf Social Debug - recipient_name: ' . $recipient_name);
        error_log('Surf Social Debug - message: ' . $message);
        
        if (empty($message) || empty($sender_id) || empty($recipient_id)) {
            error_log('Surf Social Debug - Missing required parameters');
            return new WP_Error('invalid_data', 'Message, sender ID, and recipient ID are required', array('status' => 400));
        }
        
        // Ensure table exists and has correct structure
        $this->update_individual_messages_table();
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'sender_id' => $sender_id,
                'sender_name' => sanitize_text_field($sender_name),
                'recipient_id' => $recipient_id,
                'recipient_name' => sanitize_text_field($recipient_name),
                'message' => sanitize_textarea_field($message),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            $message_id = $wpdb->insert_id;
            error_log('Surf Social Debug - Message saved successfully with ID: ' . $message_id);
            return new WP_REST_Response(array(
                'success' => true,
                'message_id' => $message_id
            ), 201);
        }
        
        error_log('Surf Social Debug - Failed to save message. Error: ' . $wpdb->last_error);
        return new WP_Error('save_failed', 'Failed to save message: ' . $wpdb->last_error, array('status' => 500));
    }
    
    /**
     * Get individual chat conversations
     */
    public function get_individual_conversations($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        
        $user_id = $request->get_param('user_id');
        
        if (empty($user_id)) {
            return new WP_Error('missing_params', 'User ID is required', array('status' => 400));
        }
        
        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    CASE 
                        WHEN sender_id = %d THEN recipient_id 
                        ELSE sender_id 
                    END as other_user_id,
                    CASE 
                        WHEN sender_id = %d THEN recipient_name 
                        ELSE sender_name 
                    END as other_user_name,
                    MAX(created_at) as last_message_time,
                    (SELECT message FROM $table_name 
                     WHERE ((sender_id = %d AND recipient_id = other_user_id) 
                            OR (sender_id = other_user_id AND recipient_id = %d))
                     ORDER BY created_at DESC LIMIT 1) as last_message
                FROM $table_name 
                WHERE sender_id = %d OR recipient_id = %d
                GROUP BY other_user_id, other_user_name
                ORDER BY last_message_time DESC",
                $user_id, $user_id, $user_id, $user_id, $user_id, $user_id
            ),
            ARRAY_A
        );
        
        return new WP_REST_Response(array('conversations' => $conversations), 200);
    }
    
    /**
     * Load user conversations for friend chat
     */
    public function load_user_conversations($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        
        $user_id = $request->get_param('user_id');
        
        if (empty($user_id)) {
            return new WP_Error('missing_params', 'User ID is required', array('status' => 400));
        }
        
        // Get all unique conversations for this user
        $conversations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    CASE 
                        WHEN sender_id = %s THEN recipient_id 
                        ELSE sender_id 
                    END as other_user_id,
                    CASE 
                        WHEN sender_id = %s THEN recipient_name 
                        ELSE sender_name 
                    END as other_user_name,
                    MAX(created_at) as last_message_time,
                    (SELECT message FROM $table_name 
                     WHERE ((sender_id = %s AND recipient_id = other_user_id) 
                            OR (sender_id = other_user_id AND recipient_id = %s))
                     ORDER BY created_at DESC LIMIT 1) as last_message
                FROM $table_name 
                WHERE sender_id = %s OR recipient_id = %s
                GROUP BY other_user_id, other_user_name
                ORDER BY last_message_time DESC",
                $user_id, $user_id, $user_id, $user_id, $user_id, $user_id
            ),
            ARRAY_A
        );
        
        return new WP_REST_Response(array('conversations' => $conversations), 200);
    }
    
    /**
     * Get support messages
     */
    public function get_support_messages($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $user_id = $request->get_param('user_id');
        $page = $request->get_param('page') ?: 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // Debug logging
        error_log("Surf Social Debug - get_support_messages called with user_id: " . $user_id);
        
        if (empty($user_id)) {
            error_log("Surf Social Debug - Missing user_id parameter");
            return new WP_Error('missing_params', 'User ID is required', array('status' => 400));
        }
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log("Surf Social Debug - Table $table_name does not exist");
            return new WP_Error('table_not_found', 'Support messages table not found', array('status' => 500));
        }
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE user_id = %s 
                ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $user_id, $per_page, $offset
            ),
            ARRAY_A
        );
        
        error_log("Surf Social Debug - Found " . count($messages) . " messages for user_id: " . $user_id);
        
        return new WP_REST_Response(array(
            'messages' => array_reverse($messages),
            'page' => $page,
            'has_more' => count($messages) === $per_page
        ), 200);
    }
    
    /**
     * Save support message
     */
    public function save_support_message($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $user_id = $request->get_param('user_id');
        $user_name = $request->get_param('user_name');
        $message = $request->get_param('message');
        $message_type = $request->get_param('message_type') ?: 'user';
        $admin_id = $request->get_param('admin_id');
        $admin_name = $request->get_param('admin_name');
        
        // Debug logging
        error_log("Surf Social Debug - save_support_message called");
        error_log("Surf Social Debug - user_id: " . $user_id);
        error_log("Surf Social Debug - user_name: " . $user_name);
        error_log("Surf Social Debug - message: " . $message);
        error_log("Surf Social Debug - message_type: " . $message_type);
        
        if (empty($message) || empty($user_id)) {
            error_log("Surf Social Debug - Missing required parameters");
            return new WP_Error('invalid_data', 'Message and user ID are required', array('status' => 400));
        }
        
        // Ensure user_name is not empty - get from existing messages if needed
        if (empty($user_name)) {
            $existing_name = $wpdb->get_var($wpdb->prepare(
                "SELECT user_name FROM $table_name WHERE user_id = %s AND user_name != '' LIMIT 1",
                $user_id
            ));
            if ($existing_name) {
                $user_name = $existing_name;
            }
        }
        
        // Convert user_id to string if it's a guest user
        if (is_string($user_id) && strpos($user_id, 'guest_') === 0) {
            // For guest users, we need to handle the user_id as a string
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'user_name' => sanitize_text_field($user_name),
                    'admin_id' => $admin_id,
                    'admin_name' => sanitize_text_field($admin_name),
                    'message' => sanitize_textarea_field($message),
                    'message_type' => sanitize_text_field($message_type),
                    'status' => 'open',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        } else {
            // For regular users, use integer
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => intval($user_id),
                    'user_name' => sanitize_text_field($user_name),
                    'admin_id' => $admin_id,
                    'admin_name' => sanitize_text_field($admin_name),
                    'message' => sanitize_textarea_field($message),
                    'message_type' => sanitize_text_field($message_type),
                    'status' => 'open',
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result) {
            $message_id = $wpdb->insert_id;
            error_log("Surf Social Debug - Message saved successfully with ID: " . $message_id);
            return new WP_REST_Response(array(
                'success' => true,
                'message_id' => $message_id
            ), 201);
        }
        
        error_log("Surf Social Debug - Failed to save message. Error: " . $wpdb->last_error);
        return new WP_Error('save_failed', 'Failed to save message: ' . $wpdb->last_error, array('status' => 500));
    }
    
    /**
     * Get support tickets for admin
     */
    public function get_support_tickets($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $tickets = $wpdb->get_results(
            "SELECT 
                user_id,
                user_name,
                MAX(created_at) as last_message_time,
                COUNT(*) as message_count,
                status,
                CASE 
                    WHEN MAX(admin_id) IS NOT NULL THEN 1 
                    ELSE 0 
                END as is_read_by_admin,
                (SELECT message FROM $table_name t2 
                 WHERE t2.user_id = t1.user_id 
                 ORDER BY t2.created_at DESC 
                 LIMIT 1) as last_message
            FROM $table_name t1
            GROUP BY user_id, user_name, status
            ORDER BY last_message_time DESC",
            ARRAY_A
        );
        
        return new WP_REST_Response(array('tickets' => $tickets), 200);
    }
    
    /**
     * AJAX handler for getting stats
     */
    public function ajax_get_stats() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $web_messages_table = $wpdb->prefix . 'surf_social_messages';
        $individual_messages_table = $wpdb->prefix . 'surf_social_individual_messages';
        $support_messages_table = $wpdb->prefix . 'surf_social_support_messages';
        $guests_table = $wpdb->prefix . 'surf_social_guests';
        
        // Helper function to safely get count
        $get_count = function($table) use ($wpdb) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            return $table_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
        };
        
        $stats = array(
            'total_web_messages' => $get_count($web_messages_table),
            'total_individual_messages' => $get_count($individual_messages_table),
            'total_support_messages' => $get_count($support_messages_table),
            'messages_today' => $wpdb->get_var("SHOW TABLES LIKE '$web_messages_table'") ? 
                $wpdb->get_var("SELECT COUNT(*) FROM $web_messages_table WHERE DATE(created_at) = CURDATE()") : 0,
            'unique_users' => $wpdb->get_var("SHOW TABLES LIKE '$web_messages_table'") ? 
                $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $web_messages_table") : 0,
            'active_support_tickets' => $wpdb->get_var("SHOW TABLES LIKE '$support_messages_table'") ? 
                $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $support_messages_table WHERE status = 'open'") : 0,
            'user_submissions' => $get_count($guests_table)
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for getting user submissions
     */
    public function ajax_get_user_submissions() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $guests_table = $wpdb->prefix . 'surf_social_guests';
        $page = intval($_GET['page']) ?: 1;
        $per_page = intval($_GET['per_page']) ?: 10;
        $sort = sanitize_text_field($_GET['sort']) ?: 'created_at';
        $direction = sanitize_text_field($_GET['direction']) ?: 'desc';
        $offset = ($page - 1) * $per_page;
        
        // Check if table exists, if not return empty results
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$guests_table'");
        if (!$table_exists) {
            wp_send_json_success(array(
                'submissions' => array(),
                'total_count' => 0,
                'total_pages' => 0,
                'current_page' => $page,
                'per_page' => $per_page
            ));
        }
        
        // Get total count
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $guests_table");
        $total_pages = ceil($total_count / $per_page);
        
        // Validate sort column and direction
        $allowed_sorts = array('name', 'email', 'created_at');
        $allowed_directions = array('asc', 'desc');
        
        if (!in_array($sort, $allowed_sorts)) {
            $sort = 'created_at';
        }
        if (!in_array($direction, $allowed_directions)) {
            $direction = 'desc';
        }
        
        // Map frontend sort names to database column names
        $sort_mapping = array(
            'name' => 'name',
            'email' => 'email',
            'date' => 'created_at'
        );
        
        $db_sort_column = isset($sort_mapping[$sort]) ? $sort_mapping[$sort] : 'created_at';
        
        // Get submissions with pagination and sorting
        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT name, email, created_at, updated_at 
                 FROM $guests_table 
                 ORDER BY $db_sort_column $direction 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'submissions' => $submissions,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * AJAX handler for getting messages
     */
    public function ajax_get_messages() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $messages_table = $wpdb->prefix . 'surf_social_messages';
        $page = intval($_GET['page']) ?: 1;
        $per_page = intval($_GET['per_page']) ?: 10;
        $offset = ($page - 1) * $per_page;
        
        // Check if table exists, if not return empty results
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$messages_table'");
        if (!$table_exists) {
            wp_send_json_success(array(
                'messages' => array(),
                'total_count' => 0,
                'total_pages' => 0,
                'current_page' => $page,
                'per_page' => $per_page
            ));
        }
        
        // Get total count
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $messages_table WHERE channel = 'web'");
        $total_pages = ceil($total_count / $per_page);
        
        // Get messages with pagination, sorted by most recent first
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, user_name, message, created_at 
                 FROM $messages_table 
                 WHERE channel = 'web'
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'messages' => $messages,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ));
    }
    
    /**
     * AJAX handler for deleting messages
     */
    public function ajax_delete_message() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $message_id = intval($_POST['message_id']);
        if (!$message_id) {
            wp_send_json_error('Invalid message ID');
        }
        
        $messages_table = $wpdb->prefix . 'surf_social_messages';
        
        // Check if message exists and is from web channel
        $message = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, user_name, message FROM $messages_table WHERE id = %d AND channel = 'web'",
            $message_id
        ));
        
        if (!$message) {
            wp_send_json_error('Message not found or not from web channel');
        }
        
        // Delete the message
        $result = $wpdb->delete(
            $messages_table,
            array('id' => $message_id),
            array('%d')
        );
        
        if ($result) {
            // Broadcast deletion event to all connected clients
            $this->broadcast_message_deletion($message_id);
            
            wp_send_json_success('Message deleted successfully');
        } else {
            wp_send_json_error('Failed to delete message');
        }
    }
    
    /**
     * Broadcast message deletion to all connected clients
     */
    private function broadcast_message_deletion($message_id) {
        // Try Pusher first
        if (get_option('surf_social_use_pusher', '1') === '1') {
            $this->broadcast_via_pusher('message-deleted', array('message_id' => $message_id));
        }
        
        // Try WebSocket as fallback
        $websocket_url = get_option('surf_social_websocket_url');
        if ($websocket_url) {
            $this->broadcast_via_websocket('message-deleted', array('message_id' => $message_id));
        }
    }
    
    /**
     * Broadcast via Pusher
     */
    private function broadcast_via_pusher($event, $data) {
        error_log("Surf Social Debug - Attempting to broadcast via Pusher: $event");
        
        // Get Pusher configuration
        $pusher_key = get_option('surf_social_pusher_key');
        $pusher_secret = get_option('surf_social_pusher_secret');
        $pusher_cluster = get_option('surf_social_pusher_cluster');
        
        if (empty($pusher_key) || empty($pusher_secret)) {
            error_log("Surf Social Debug - Pusher not configured properly");
            return false;
        }
        
        // Use WordPress HTTP API to send to Pusher
        $pusher_url = "https://api.pusherapp.com/apps/$pusher_key/events";
        
        $body = array(
            'name' => $event,
            'data' => json_encode($data),
            'channel' => 'surf-social-channel'
        );
        
        $response = wp_remote_post($pusher_url, array(
            'body' => $body,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log("Surf Social Debug - Pusher broadcast failed: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            error_log("Surf Social Debug - Pusher broadcast successful");
            return true;
        } else {
            error_log("Surf Social Debug - Pusher broadcast failed with code: $response_code");
            return false;
        }
    }
    
    /**
     * Broadcast via WebSocket
     */
    private function broadcast_via_websocket($event, $data) {
        error_log("Surf Social Debug - Attempting to broadcast via WebSocket: $event");
        
        $websocket_url = get_option('surf_social_websocket_url');
        if (empty($websocket_url)) {
            error_log("Surf Social Debug - WebSocket URL not configured");
            return false;
        }
        
        $payload = json_encode(array(
            'type' => $event,
            'data' => $data
        ));
        
        $response = wp_remote_post($websocket_url, array(
            'body' => $payload,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            error_log("Surf Social Debug - WebSocket broadcast failed: " . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            error_log("Surf Social Debug - WebSocket broadcast successful");
            return true;
        } else {
            error_log("Surf Social Debug - WebSocket broadcast failed with code: $response_code");
            return false;
        }
    }
    
    /**
     * Broadcast admin reply to frontend
     */
    private function broadcast_admin_reply($user_id, $user_name, $message, $admin_name) {
        error_log("Surf Social Debug - broadcast_admin_reply called");
        error_log("Surf Social Debug - user_id: $user_id");
        error_log("Surf Social Debug - user_name: $user_name");
        error_log("Surf Social Debug - message: $message");
        error_log("Surf Social Debug - admin_name: $admin_name");
        
        $data = array(
            'type' => 'admin-support-reply',
            'user_id' => $user_id,
            'user_name' => $user_name,
            'admin_name' => $admin_name,
            'message' => $message,
            'created_at' => current_time('mysql')
        );
        
        error_log("Surf Social Debug - Broadcasting data: " . json_encode($data));
        
        $pusher_success = false;
        $websocket_success = false;
        
        // Try Pusher first
        if (get_option('surf_social_use_pusher', '1') === '1') {
            error_log("Surf Social Debug - Attempting Pusher broadcast");
            $pusher_success = $this->broadcast_via_pusher('admin-support-reply', $data);
        } else {
            error_log("Surf Social Debug - Pusher disabled");
        }
        
        // Try WebSocket as fallback
        $websocket_url = get_option('surf_social_websocket_url');
        if ($websocket_url) {
            error_log("Surf Social Debug - Attempting WebSocket broadcast");
            $websocket_success = $this->broadcast_via_websocket('admin-support-reply', $data);
        } else {
            error_log("Surf Social Debug - WebSocket URL not configured");
        }
        
        error_log("Surf Social Debug - Broadcast results - Pusher: " . ($pusher_success ? 'success' : 'failed') . ", WebSocket: " . ($websocket_success ? 'success' : 'failed'));
        
        // If both fail, we'll rely on polling/refresh
        if (!$pusher_success && !$websocket_success) {
            error_log("Surf Social Debug - Both broadcasting methods failed, relying on polling");
        }
    }
    
    /**
     * AJAX handler for getting support tickets
     */
    public function ajax_get_support_tickets() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        // Debug logging
        error_log("Surf Social Debug - ajax_get_support_tickets called");
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            error_log("Surf Social Debug - Table $table_name does not exist");
            wp_send_json_error('Support messages table not found');
        }
        
        // Get total count first
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        error_log("Surf Social Debug - Total messages in table: $total_count");
        
        $tickets = $wpdb->get_results(
            "SELECT 
                user_id,
                user_name,
                MAX(created_at) as last_message_time,
                COUNT(*) as message_count,
                status,
                CASE 
                    WHEN MAX(admin_id) IS NOT NULL THEN 1 
                    ELSE 0 
                END as is_read_by_admin,
                (SELECT message FROM $table_name t2 
                 WHERE t2.user_id = t1.user_id 
                 ORDER BY t2.created_at DESC 
                 LIMIT 1) as last_message
            FROM $table_name t1
            GROUP BY user_id, user_name, status
            ORDER BY last_message_time DESC",
            ARRAY_A
        );
        
        error_log("Surf Social Debug - Found " . count($tickets) . " tickets");
        if (count($tickets) > 0) {
            error_log("Surf Social Debug - First ticket: " . print_r($tickets[0], true));
        }
        
        wp_send_json_success(array('tickets' => $tickets));
    }
    
    /**
     * AJAX handler for getting support conversation
     */
    public function ajax_get_support_conversation() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $user_id = sanitize_text_field($_GET['user_id']);
        if (!$user_id) {
            wp_send_json_error('User ID is required');
        }
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE user_id = %s 
                ORDER BY created_at ASC",
                $user_id
            ),
            ARRAY_A
        );
        
        // Get user info
        $user_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_name, user_id FROM $table_name WHERE user_id = %s LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        
        wp_send_json_success(array(
            'messages' => $messages,
            'user_info' => $user_info
        ));
    }
    
    /**
     * AJAX handler for sending admin reply
     */
    public function ajax_send_admin_reply() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $user_id = sanitize_text_field($_POST['user_id']);
        $message = sanitize_textarea_field($_POST['message']);
        $admin_id = get_current_user_id();
        $admin_name = wp_get_current_user()->display_name ?: wp_get_current_user()->user_login;
        
        // Debug logging
        error_log("Surf Social Debug - ajax_send_admin_reply called");
        error_log("Surf Social Debug - user_id: $user_id");
        error_log("Surf Social Debug - message: $message");
        error_log("Surf Social Debug - admin_id: $admin_id");
        error_log("Surf Social Debug - admin_name: $admin_name");
        
        if (!$user_id || !$message) {
            error_log("Surf Social Debug - Missing required parameters");
            wp_send_json_error('User ID and message are required');
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'user_name' => '', // Will be filled from existing messages
                'admin_id' => $admin_id,
                'admin_name' => $admin_name,
                'message' => $message,
                'message_type' => 'admin',
                'status' => 'open',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            error_log("Surf Social Debug - Admin reply saved successfully with ID: " . $wpdb->insert_id);
            
            // Get the user name from existing messages
            $user_name = $wpdb->get_var($wpdb->prepare(
                "SELECT user_name FROM $table_name WHERE user_id = %s LIMIT 1",
                $user_id
            ));
            
            error_log("Surf Social Debug - User name: $user_name");
            
            // Broadcast the admin reply to the frontend
            $this->broadcast_admin_reply($user_id, $user_name, $message, $admin_name);
            
            wp_send_json_success('Reply sent successfully');
        } else {
            error_log("Surf Social Debug - Failed to save admin reply. Error: " . $wpdb->last_error);
            wp_send_json_error('Failed to send reply: ' . $wpdb->last_error);
        }
    }
    
    /**
     * AJAX handler for marking support as read
     */
    public function ajax_mark_support_read() {
        check_ajax_referer('surf_social_stats', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $user_id = sanitize_text_field($_POST['user_id']);
        if (!$user_id) {
            wp_send_json_error('User ID is required');
        }
        
        // Update all unread messages for this user to mark them as read by admin
        $result = $wpdb->update(
            $table_name,
            array(
                'admin_id' => get_current_user_id(),
                'admin_name' => wp_get_current_user()->display_name ?: wp_get_current_user()->user_login
            ),
            array(
                'user_id' => $user_id,
                'admin_id' => null
            ),
            array('%d', '%s'),
            array('%s', 'NULL')
        );
        
        // Also update any messages that don't have admin_id set (for backward compatibility)
        $wpdb->update(
            $table_name,
            array(
                'admin_id' => get_current_user_id(),
                'admin_name' => wp_get_current_user()->display_name ?: wp_get_current_user()->user_login
            ),
            array(
                'user_id' => $user_id,
                'admin_id' => 0
            ),
            array('%d', '%s'),
            array('%s', '%d')
        );
        
        // Also update the user_name if it's empty
        $wpdb->update(
            $table_name,
            array('user_name' => $wpdb->get_var($wpdb->prepare(
                "SELECT user_name FROM $table_name WHERE user_id = %s AND user_name != '' LIMIT 1",
                $user_id
            ))),
            array('user_id' => $user_id, 'user_name' => ''),
            array('%s'),
            array('%s', '%s')
        );
        
        wp_send_json_success('Marked as read');
    }
    
    /**
     * Get plugin statistics
     */
    public function get_stats($request) {
        global $wpdb;
        
        $web_messages_table = $wpdb->prefix . 'surf_social_messages';
        $individual_messages_table = $wpdb->prefix . 'surf_social_individual_messages';
        $support_messages_table = $wpdb->prefix . 'surf_social_support_messages';
        $guests_table = $wpdb->prefix . 'surf_social_guests';
        
        $stats = array(
            'total_web_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $web_messages_table"),
            'total_individual_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $individual_messages_table"),
            'total_support_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $support_messages_table"),
            'messages_today' => $wpdb->get_var("SELECT COUNT(*) FROM $web_messages_table WHERE DATE(created_at) = CURDATE()"),
            'unique_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $web_messages_table"),
            'active_support_tickets' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $support_messages_table WHERE status = 'open'"),
            'user_submissions' => $wpdb->get_var("SELECT COUNT(*) FROM $guests_table")
        );
        
        return new WP_REST_Response($stats, 200);
    }
    
    /**
     * Register guest user
     */
    public function register_guest($request) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_guests';
        
        $user_id = $request->get_param('user_id');
        $name = $request->get_param('name');
        $email = $request->get_param('email');
        
        if (empty($user_id) || empty($name) || empty($email)) {
            return new WP_Error('invalid_data', 'User ID, name, and email are required', array('status' => 400));
        }
        
        // Validate email format
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email format', array('status' => 400));
        }
        
        // Check if guest already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %s",
            $user_id
        ));
        
        if ($existing) {
            // Update existing guest
            $result = $wpdb->update(
                $table_name,
                array(
                    'name' => sanitize_text_field($name),
                    'email' => sanitize_email($email),
                    'updated_at' => current_time('mysql')
                ),
                array('user_id' => $user_id),
                array('%s', '%s', '%s'),
                array('%s')
            );
        } else {
            // Insert new guest
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'name' => sanitize_text_field($name),
                    'email' => sanitize_email($email),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
        }
        
        if ($result !== false) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Guest registered successfully'
            ), 200);
        }
        
        return new WP_Error('save_failed', 'Failed to register guest', array('status' => 500));
    }
    
    /**
     * Check admin permission
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Debug AJAX handler for database check
     */
    public function ajax_debug_database() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        
        $tables = array();
        $support_messages_count = 0;
        $guest_users_count = 0;
        
        // Check if tables exist
        $table_names = array(
            $wpdb->prefix . 'surf_social_messages',
            $wpdb->prefix . 'surf_social_individual_messages',
            $wpdb->prefix . 'surf_social_support_messages',
            $wpdb->prefix . 'surf_social_guests'
        );
        
        foreach ($table_names as $table_name) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($exists) {
                $tables[] = str_replace($wpdb->prefix, '', $table_name);
                
                if (strpos($table_name, 'support_messages') !== false) {
                    $support_messages_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                }
                if (strpos($table_name, 'guests') !== false) {
                    $guest_users_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                }
            }
        }
        
        wp_send_json_success(array(
            'tables' => $tables,
            'support_messages_count' => $support_messages_count,
            'guest_users_count' => $guest_users_count
        ));
    }
    
    /**
     * Debug AJAX handler for configuration check
     */
    public function ajax_debug_config() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        wp_send_json_success(array(
            'pusher_key' => get_option('surf_social_pusher_key', 'Not set'),
            'pusher_cluster' => get_option('surf_social_pusher_cluster', 'Not set'),
            'use_pusher' => get_option('surf_social_use_pusher', 'Not set'),
            'websocket_url' => get_option('surf_social_websocket_url', 'Not set'),
            'enabled' => get_option('surf_social_enabled', 'Not set')
        ));
    }
    
    /**
     * Debug AJAX handler for live logs
     */
    public function ajax_debug_live_logs() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Get recent error logs
        $logs = array();
        $error_log = ini_get('error_log');
        
        if ($error_log && file_exists($error_log)) {
            $lines = file($error_log, FILE_IGNORE_NEW_LINES);
            $recent_lines = array_slice($lines, -10); // Last 10 lines
            
            foreach ($recent_lines as $line) {
                if (strpos($line, 'surf') !== false || strpos($line, 'Surf') !== false) {
                    $logs[] = array(
                        'timestamp' => date('H:i:s'),
                        'type' => 'info',
                        'message' => $line
                    );
                }
            }
        }
        
        wp_send_json_success(array('logs' => $logs));
    }
    
    /**
     * Debug AJAX handler for fixing tables
     */
    public function ajax_debug_fix_tables() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Recreate tables
        $this->ensure_database_tables();
        
        wp_send_json_success(array(
            'message' => 'Database tables have been recreated successfully.'
        ));
    }
    
    /**
     * Debug AJAX handler for clearing messages
     */
    public function ajax_debug_clear_messages() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        wp_send_json_success(array(
            'count' => $count
        ));
    }
    
    /**
     * Debug AJAX handler for resetting settings
     */
    public function ajax_debug_reset_settings() {
        check_ajax_referer('surf_social_debug', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Reset to default values
        update_option('surf_social_pusher_key', 'c08d09b4013a00d6a626');
        update_option('surf_social_pusher_secret', '15a73a9dbb0a4884f6fa');
        update_option('surf_social_pusher_cluster', 'us3');
        update_option('surf_social_use_pusher', '1');
        update_option('surf_social_enabled', '1');
        update_option('surf_social_websocket_url', '');
        
        wp_send_json_success();
    }
    
    /**
     * Test function to verify support chat functionality
     */
    public function test_support_chat() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        
        $test_user_id = 'test_' . time();
        $test_message = 'Test message from admin panel at ' . current_time('mysql');
        
        // Insert a test message
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $test_user_id,
                'user_name' => 'Test User',
                'admin_id' => get_current_user_id(),
                'admin_name' => wp_get_current_user()->display_name,
                'message' => $test_message,
                'message_type' => 'admin',
                'status' => 'open',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Test retrieving the message
            $tickets = $wpdb->get_results(
                "SELECT 
                    user_id,
                    user_name,
                    MAX(created_at) as last_message_time,
                    COUNT(*) as message_count,
                    status,
                    CASE 
                        WHEN MAX(admin_id) IS NOT NULL THEN 1 
                        ELSE 0 
                    END as is_read_by_admin,
                    (SELECT message FROM $table_name t2 
                     WHERE t2.user_id = t1.user_id 
                     ORDER BY t2.created_at DESC 
                     LIMIT 1) as last_message
                FROM $table_name t1
                WHERE user_id = '$test_user_id'
                GROUP BY user_id, user_name, status
                ORDER BY last_message_time DESC",
                ARRAY_A
            );
            
            if (count($tickets) > 0) {
                echo "✅ Test successful! Found " . count($tickets) . " tickets for test user.<br>";
                echo "Test ticket: " . print_r($tickets[0], true);
            } else {
                echo "❌ Test failed! No tickets found for test user.";
            }
            
            // Clean up test data
            $wpdb->delete($table_name, array('user_id' => $test_user_id));
        } else {
            echo "❌ Test failed! Could not insert test message. Error: " . $wpdb->last_error;
        }
    }
    
    /**
     * Create database tables on activation
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Main messages table
        $table_name = $wpdb->prefix . 'surf_social_messages';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_name varchar(100) NOT NULL,
            message text NOT NULL,
            channel varchar(50) NOT NULL DEFAULT 'web',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY channel (channel),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Individual messages table
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id varchar(100) NOT NULL,
            sender_name varchar(100) NOT NULL,
            recipient_id varchar(100) NOT NULL,
            recipient_name varchar(100) NOT NULL,
            message text NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime NULL,
            PRIMARY KEY  (id),
            KEY sender_id (sender_id),
            KEY recipient_id (recipient_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Update existing table to use varchar for user IDs if needed
        $this->update_individual_messages_table();
        
        // Support messages table
        $table_name = $wpdb->prefix . 'surf_social_support_messages';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id varchar(100) NOT NULL,
            user_name varchar(100) NOT NULL,
            admin_id bigint(20) NULL,
            admin_name varchar(100) NULL,
            message text NOT NULL,
            message_type enum('user', 'admin') DEFAULT 'user',
            status enum('open', 'closed', 'resolved') DEFAULT 'open',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Guest users table
        $table_name = $wpdb->prefix . 'surf_social_guests';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id varchar(100) NOT NULL,
            name varchar(100) NOT NULL,
            email varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY email (email),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Set default options with your Pusher credentials
        add_option('surf_social_enabled', '1');
        add_option('surf_social_use_pusher', '1');
        add_option('surf_social_pusher_key', 'c08d09b4013a00d6a626');
        add_option('surf_social_pusher_secret', '15a73a9dbb0a4884f6fa');
        add_option('surf_social_pusher_cluster', 'us3');
        add_option('surf_social_websocket_url', '');
    }
    
    /**
     * Update individual messages table to support string user IDs
     */
    private function update_individual_messages_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            return;
        }
        
        // Check if sender_id is already varchar
        $column_info = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'sender_id'");
        if (!empty($column_info) && strpos($column_info[0]->Type, 'varchar') === false) {
            // Update sender_id column to varchar
            $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN sender_id varchar(100) NOT NULL");
        }
        
        // Check if recipient_id is already varchar
        $column_info = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'recipient_id'");
        if (!empty($column_info) && strpos($column_info[0]->Type, 'varchar') === false) {
            // Update recipient_id column to varchar
            $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN recipient_id varchar(100) NOT NULL");
        }
    }
    
    /**
     * Clean up on deactivation
     */
    public static function deactivate() {
        // Optional: Clean up if needed
    }
}

// Initialize plugin
Surf_Social::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Surf_Social', 'activate'));
register_deactivation_hook(__FILE__, array('Surf_Social', 'deactivate'));

