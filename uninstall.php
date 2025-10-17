<?php
/**
 * Uninstall Surf Social
 * 
 * This file is executed when the plugin is deleted
 */

// Exit if accessed directly
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}surf_social_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}surf_social_individual_messages");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}surf_social_support_messages");

// Remove options
delete_option('surf_social_enabled');
delete_option('surf_social_pusher_key');
delete_option('surf_social_pusher_secret');
delete_option('surf_social_pusher_cluster');
delete_option('surf_social_use_pusher');
delete_option('surf_social_websocket_url');

