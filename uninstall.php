<?php
declare(strict_types=1);
/**
 * Plugin Uninstall Script
 * 
 * Fired when the plugin is uninstalled. Handles complete cleanup of all
 * plugin data, options, database tables, and files.
 * 
 * @package SEOForge
 * @since 2.0.0
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check
if (!current_user_can('activate_plugins')) {
    exit;
}

// Check if we should preserve data
$preserve_data = get_option('seo_forge_preserve_data_on_uninstall', false);

if (!$preserve_data) {
    // Delete all plugin options
    delete_option('seo_forge_version');
    delete_option('seo_forge_settings');
    delete_option('seo_forge_activated_at');
    delete_option('seo_forge_deactivated_at');
    delete_option('seo_forge_activation_redirect');
    delete_option('seo_forge_blocked_ips');
    delete_option('seo_forge_preserve_data_on_uninstall');
    
    // Delete all transients
    global $wpdb;
    
    $wpdb->query(
        "DELETE FROM {$wpdb->options} 
         WHERE option_name LIKE '_transient_seo_forge_%' 
         OR option_name LIKE '_transient_timeout_seo_forge_%'"
    );
    
    // Drop custom database tables
    $tables = [
        'seo_forge_content',
        'seo_forge_analytics',
        'seo_forge_keywords',
        'seo_forge_templates',
        'seo_forge_settings',
        'seo_forge_logs',
        'seo_forge_security_events',
        'seo_forge_drafts', // Legacy table
    ];
    
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
    
    // Remove custom post types and their posts
    $custom_post_types = ['seo_content', 'blog_template'];
    
    foreach ($custom_post_types as $post_type) {
        $posts = get_posts([
            'post_type' => $post_type,
            'numberposts' => -1,
            'post_status' => 'any',
        ]);
        
        foreach ($posts as $post) {
            wp_delete_post($post->ID, true);
        }
    }
    
    // Remove custom taxonomies and their terms
    $custom_taxonomies = ['seo_category'];
    
    foreach ($custom_taxonomies as $taxonomy) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                wp_delete_term($term->term_id, $taxonomy);
            }
        }
    }
    
    // Remove custom capabilities
    $capabilities = [
        'manage_seo_forge',
        'edit_seo_content',
        'publish_seo_content',
        'delete_seo_content',
        'view_seo_analytics',
        'manage_seo_settings',
        'use_ai_generator',
    ];
    
    // Remove capabilities from all roles
    global $wp_roles;
    
    if (isset($wp_roles)) {
        foreach ($wp_roles->roles as $role_name => $role_info) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($capabilities as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
    }
    
    // Remove custom roles
    remove_role('seo_manager');
    remove_role('seo_editor');
    
    // Clear scheduled events
    $scheduled_events = [
        'seo_forge_generate_content',
        'seo_forge_sync_analytics',
        'seo_forge_keyword_research',
        'seo_forge_cleanup',
        'seo_forge_security_audit',
    ];
    
    foreach ($scheduled_events as $event) {
        wp_clear_scheduled_hook($event);
    }
    
    // Remove plugin files and directories
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/seo-forge';
    
    if (is_dir($plugin_upload_dir)) {
        removeDirectory($plugin_upload_dir);
    }
    
    // Remove user meta related to plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
         WHERE meta_key LIKE 'seo_forge_%'"
    );
    
    // Remove post meta related to plugin (including legacy meta)
    $meta_keys = [
        '_seo_forge_meta_description',
        '_seo_forge_focus_keyword',
        '_seo_forge_seo_score',
        'seo_forge_keyword',
        'seo_forge_language',
        'seo_forge_generated',
    ];
    
    foreach ($meta_keys as $meta_key) {
        $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
    }
    
    // Remove term meta related to plugin
    $wpdb->query(
        "DELETE FROM {$wpdb->termmeta} 
         WHERE meta_key LIKE 'seo_forge_%'"
    );
    
    // Clear any cached data
    wp_cache_flush();
    
    // Log uninstall
    error_log('SEO-Forge plugin uninstalled and all data removed');
}

/**
 * Recursively remove directory and its contents
 * 
 * @param string $dir Directory path
 */
function removeDirectory(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    rmdir($dir);
}
