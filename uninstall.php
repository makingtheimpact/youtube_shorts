<?php
/**
 * Uninstall YouTube Shorts Slider Plugin
 * 
 * This file is executed when the plugin is uninstalled.
 * It removes all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permission to uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

// Remove plugin options
delete_option('youtube_api_key');
delete_option('youtube_shorts_defaults');

// Clear all transients created by this plugin
global $wpdb;

// Find and delete all transients with our prefix
$like_value = $wpdb->esc_like('_transient_yt_shorts_') . '%';
$like_timeout = $wpdb->esc_like('_transient_timeout_yt_shorts_') . '%';

$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_value));
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout));

// Clear any object cache if available
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Log uninstall for debugging (if debug is enabled)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('YouTube Shorts Slider plugin uninstalled - all data cleaned up');
}
