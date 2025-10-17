<?php
/*
Plugin Name: Surf Social
Plugin URI: https://github.com/tommypf11/surf-social
GitHub Plugin URI: https://github.com/tommypf11/surf-social
Description: Your plugin description
Version: 1.0.23
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_footer', array($this, 'render_chat_widget'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('init', array($this, 'add_cors_headers'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        load_plugin_textdomain('surf-social', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on frontend
        if (is_admin()) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'surf-social-style',
            SURF_SOCIAL_PLUGIN_URL . 'assets/css/surf-social.css',
            array(),
            SURF_SOCIAL_VERSION
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
        } else {
            $user_name = $current_user->display_name ?: $current_user->user_login;
        }
        
        // Ensure we always have a user ID and name
        if (empty($user_id)) {
            $user_id = 'guest_' . uniqid();
            $user_name = 'Guest User';
        }
        if (empty($user_name)) {
            $user_name = 'Guest User';
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
                'avatar' => $avatar_url,
                'color' => $this->get_user_color($user_id),
                'isGuest' => !get_current_user_id()
            ),
            'apiUrl' => rest_url('surf-social/v1/'),
            'nonce' => wp_create_nonce('wp_rest')
        ));
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
        
        if (empty($user_id) || empty($target_user_id)) {
            return new WP_Error('missing_params', 'User ID and target user ID are required', array('status' => 400));
        }
        
        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE (sender_id = %d AND recipient_id = %d) 
                OR (sender_id = %d AND recipient_id = %d)
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
            array('%d', '%s', '%d', '%s', '%s', '%s')
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
                WHERE user_id = %d 
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
            array('%d', '%s', '%d', '%s', '%s', '%s', '%s')
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
     * Get plugin statistics
     */
    public function get_stats($request) {
        global $wpdb;
        
        $web_messages_table = $wpdb->prefix . 'surf_social_messages';
        $individual_messages_table = $wpdb->prefix . 'surf_social_individual_messages';
        $support_messages_table = $wpdb->prefix . 'surf_social_support_messages';
        
        $stats = array(
            'total_web_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $web_messages_table"),
            'total_individual_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $individual_messages_table"),
            'total_support_messages' => $wpdb->get_var("SELECT COUNT(*) FROM $support_messages_table"),
            'messages_today' => $wpdb->get_var("SELECT COUNT(*) FROM $web_messages_table WHERE DATE(created_at) = CURDATE()"),
            'unique_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $web_messages_table"),
            'active_support_tickets' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $support_messages_table WHERE status = 'open'")
        );
        
        return new WP_REST_Response($stats, 200);
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
            user_id bigint(20) NOT NULL,
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
}

// Initialize plugin
Surf_Social::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Surf_Social', 'activate'));
register_deactivation_hook(__FILE__, array('Surf_Social', 'deactivate'));

