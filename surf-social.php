<?php
/*
Plugin Name: Surf Social
Plugin URI: https://github.com/tommypf11/surf-social
GitHub Plugin URI: https://github.com/tommypf11/surf-social
Description: Your plugin description
Version: 1.0.50
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
        add_action('wp_ajax_surf_social_close_support_ticket', array($this, 'ajax_close_support_ticket'));
        add_action('wp_ajax_surf_social_debug_database', array($this, 'ajax_debug_database'));
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
            array('%s', '%s', '%s', '%s', '%s')
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
        
        if (empty($user_id) || empty($target_user_id)) {
            return new WP_Error('missing_params', 'User ID and target user ID are required', array('status' => 400));
        }
        
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
        
        if (empty($message) || empty($sender_id) || empty($recipient_id)) {
            return new WP_Error('invalid_data', 'Message, sender ID, and recipient ID are required', array('status' => 400));
        }
        
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
            return new WP_REST_Response(array(
                'success' => true,
                'message_id' => $message_id
            ), 201);
        }
        
        return new WP_Error('save_failed', 'Failed to save message', array('status' => 500));
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
        
        if (empty($user_id)) {
            return new WP_Error('missing_params', 'User ID is required', array('status' => 400));
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
        
        if (empty($message) || empty($user_id)) {
            return new WP_Error('invalid_data', 'Message and user ID are required', array('status' => 400));
        }
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'user_name' => sanitize_text_field($user_name),
                'admin_id' => $admin_id,
                'admin_name' => sanitize_text_field($admin_name),
                'message' => sanitize_textarea_field($message),
                'message_type' => sanitize_text_field($message_type),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s')
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
                status
            FROM $table_name 
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
        // For now, we'll rely on the frontend to handle real-time updates
        // In a full implementation, you'd use the Pusher PHP SDK here
        // The frontend will handle the deletion through the admin interface
    }
    
    /**
     * Broadcast via WebSocket
     */
    private function broadcast_via_websocket($event, $data) {
        // For now, we'll rely on the frontend to handle real-time updates
        // In a full implementation, you'd use a WebSocket server here
        // The frontend will handle the deletion through the admin interface
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
            user_id varchar(100) NOT NULL,
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
        
        // Migrate existing tables to use varchar for user_id
        $this->migrate_user_id_columns();
        
        // Individual messages table
        $table_name = $wpdb->prefix . 'surf_social_individual_messages';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            sender_id bigint(20) NOT NULL,
            sender_name varchar(100) NOT NULL,
            recipient_id bigint(20) NOT NULL,
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
     * Clean up on deactivation
     */
    public static function deactivate() {
        // Optional: Clean up if needed
    }
    
    /**
     * Migrate user_id columns from bigint to varchar
     */
    private function migrate_user_id_columns() {
        global $wpdb;
        
        // Check if migration is needed
        $messages_table = $wpdb->prefix . 'surf_social_messages';
        $support_table = $wpdb->prefix . 'surf_social_support_messages';
        
        // Check if user_id column is still bigint
        $messages_column = $wpdb->get_row("SHOW COLUMNS FROM $messages_table LIKE 'user_id'");
        $support_column = $wpdb->get_row("SHOW COLUMNS FROM $support_table LIKE 'user_id'");
        
        if ($messages_column && strpos($messages_column->Type, 'bigint') !== false) {
            $wpdb->query("ALTER TABLE $messages_table MODIFY COLUMN user_id varchar(100) NOT NULL");
        }
        
        if ($support_column && strpos($support_column->Type, 'bigint') !== false) {
            $wpdb->query("ALTER TABLE $support_table MODIFY COLUMN user_id varchar(100) NOT NULL");
        }
    }
    
    /**
     * AJAX handler for getting support tickets
     */
    public function ajax_get_support_tickets() {
        check_ajax_referer('surf_social_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        
        global $wpdb;
        $support_table = $wpdb->prefix . 'surf_social_support_messages';
        $status_filter = sanitize_text_field($_GET['status'] ?? 'all');
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$support_table'");
        if (!$table_exists) {
            wp_send_json_success(array('tickets' => array()));
        }
        
        $where_clause = '';
        if ($status_filter === 'open') {
            $where_clause = "WHERE status = 'open'";
        } elseif ($status_filter === 'closed') {
            $where_clause = "WHERE status = 'closed'";
        }
        
        $tickets = $wpdb->get_results(
            "SELECT 
                user_id,
                user_name,
                MAX(created_at) as last_message_time,
                COUNT(*) as message_count,
                status,
                (SELECT message FROM $support_table s2 
                 WHERE s2.user_id = s1.user_id 
                 ORDER BY created_at DESC LIMIT 1) as last_message
            FROM $support_table s1 
            $where_clause
            GROUP BY user_id, user_name, status
            ORDER BY last_message_time DESC",
            ARRAY_A
        );
        
        wp_send_json_success(array('tickets' => $tickets));
    }
    
    /**
     * AJAX handler for getting support conversation
     */
    public function ajax_get_support_conversation() {
        check_ajax_referer('surf_social_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        
        global $wpdb;
        $support_table = $wpdb->prefix . 'surf_social_support_messages';
        $user_id = $_GET['user_id'];
        
        if (empty($user_id) && $user_id !== '0' && $user_id !== 0) {
            wp_send_json_error('User ID required');
        }
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$support_table'");
        if (!$table_exists) {
            wp_send_json_success(array('messages' => array(), 'user_info' => null));
        }
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $support_table 
                WHERE user_id = %s 
                ORDER BY created_at ASC",
                $user_id
            ),
            ARRAY_A
        );
        
        $user_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, user_name, status FROM $support_table 
                WHERE user_id = %s 
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        
        
        wp_send_json_success(array('messages' => $messages, 'user_info' => $user_info));
    }
    
    /**
     * AJAX handler for sending admin reply
     */
    public function ajax_send_admin_reply() {
        check_ajax_referer('surf_social_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        
        global $wpdb;
        $support_table = $wpdb->prefix . 'surf_social_support_messages';
        $user_id = $_POST['user_id'];
        $message = sanitize_textarea_field($_POST['message']);
        $admin_id = get_current_user_id();
        $admin_name = wp_get_current_user()->display_name;
        
        if ((empty($user_id) && $user_id !== '0' && $user_id !== 0) || empty($message)) {
            wp_send_json_error('User ID and message required');
        }
        
        $result = $wpdb->insert(
            $support_table,
            array(
                'user_id' => $user_id,
                'user_name' => $wpdb->get_var($wpdb->prepare("SELECT user_name FROM $support_table WHERE user_id = %d LIMIT 1", $user_id)),
                'admin_id' => $admin_id,
                'admin_name' => $admin_name,
                'message' => $message,
                'message_type' => 'admin',
                'status' => 'open',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result) {
            // Broadcast to user via real-time
            $this->broadcast_admin_reply($user_id, $message, $admin_name);
            wp_send_json_success('Reply sent successfully');
        } else {
            wp_send_json_error('Failed to send reply');
        }
    }
    
    /**
     * AJAX handler for closing support ticket
     */
    public function ajax_close_support_ticket() {
        check_ajax_referer('surf_social_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        
        global $wpdb;
        $support_table = $wpdb->prefix . 'surf_social_support_messages';
        $user_id = $_POST['user_id'];
        if (empty($user_id) && $user_id !== '0' && $user_id !== 0) {
            wp_send_json_error('User ID required');
        }
        
        $result = $wpdb->update(
            $support_table,
            array('status' => 'closed'),
            array('user_id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Ticket closed successfully');
        } else {
            wp_send_json_error('Failed to close ticket');
        }
    }
    
    /**
     * Broadcast admin reply to user
     */
    private function broadcast_admin_reply($user_id, $message, $admin_name) {
        $pusher_key = get_option('surf_social_pusher_key');
        $websocket_url = get_option('surf_social_websocket_url');
        
        if ($pusher_key) {
            $this->broadcast_via_pusher('admin-support-reply', array(
                'user_id' => $user_id,
                'message' => $message,
                'admin_name' => $admin_name,
                'created_at' => current_time('mysql')
            ));
        }
        
        if ($websocket_url) {
            $this->broadcast_via_websocket('admin-support-reply', array(
                'user_id' => $user_id,
                'message' => $message,
                'admin_name' => $admin_name,
                'created_at' => current_time('mysql')
            ));
        }
    }
    
    /**
     * AJAX handler for debugging database state
     */
    public function ajax_debug_database() {
        check_ajax_referer('surf_social_stats', 'nonce');
        if (!current_user_can('manage_options')) { wp_die('Unauthorized'); }
        
        global $wpdb;
        
        $debug_info = array();
        
        // Check table schemas
        $tables = array(
            'surf_social_messages',
            'surf_social_individual_messages', 
            'surf_social_support_messages'
        );
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($table_exists) {
                // Get column info
                $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name", ARRAY_A);
                $user_id_column = null;
                foreach ($columns as $column) {
                    if ($column['Field'] === 'user_id') {
                        $user_id_column = $column;
                        break;
                    }
                }
                
                // Get sample data
                $sample_data = $wpdb->get_results(
                    "SELECT user_id, user_name, message, created_at FROM $table_name ORDER BY created_at DESC LIMIT 5",
                    ARRAY_A
                );
                
                // Get unique user_ids
                $unique_users = $wpdb->get_results(
                    "SELECT user_id, user_name, COUNT(*) as message_count FROM $table_name GROUP BY user_id, user_name ORDER BY message_count DESC",
                    ARRAY_A
                );
                
                $debug_info[$table] = array(
                    'exists' => true,
                    'user_id_column' => $user_id_column,
                    'sample_data' => $sample_data,
                    'unique_users' => $unique_users,
                    'total_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name")
                );
            } else {
                $debug_info[$table] = array('exists' => false);
            }
        }
        
        wp_send_json_success($debug_info);
    }
    
    
}

// Initialize plugin
Surf_Social::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Surf_Social', 'activate'));
register_deactivation_hook(__FILE__, array('Surf_Social', 'deactivate'));

