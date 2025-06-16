<?php
/**
 * Plugin Name: SEO-Forge Professional
 * Plugin URI: https://seo-forge.bitebase.app
 * Description: Professional WordPress plugin with advanced SEO capabilities, AI-powered blog generation, content management, and comprehensive analytics. Built with modern architecture and security best practices.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: SEO-Forge Team
 * Author URI: https://seo-forge.bitebase.app
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: seo-forge
 * Domain Path: /languages
 * Network: false
 * Update URI: https://seo-forge.bitebase.app/updates
 *
 * @package SEOForge
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Plugin constants (only if not already defined)
if (!defined('SEO_FORGE_VERSION')) {
    define('SEO_FORGE_VERSION', '2.0.0');
}
if (!defined('SEO_FORGE_MIN_PHP')) {
    define('SEO_FORGE_MIN_PHP', '8.0');
}
if (!defined('SEO_FORGE_MIN_WP')) {
    define('SEO_FORGE_MIN_WP', '6.0');
}

// Define plugin paths and URLs (only if not already defined)
if (!defined('SEO_FORGE_FILE')) {
    define('SEO_FORGE_FILE', __FILE__);
}
if (!defined('SEO_FORGE_PATH')) {
    define('SEO_FORGE_PATH', plugin_dir_path(__FILE__));
}
if (!defined('SEO_FORGE_URL')) {
    define('SEO_FORGE_URL', plugin_dir_url(__FILE__));
}
if (!defined('SEO_FORGE_BASENAME')) {
    define('SEO_FORGE_BASENAME', plugin_basename(__FILE__));
}

/**
 * Check system requirements before loading the plugin
 */
function seo_forge_check_requirements() {
    global $wp_version;
    
    // Check PHP version
    if (version_compare(PHP_VERSION, SEO_FORGE_MIN_PHP, '<')) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    esc_html__('SEO-Forge requires PHP %1$s or higher. You are running PHP %2$s.', 'seo-forge'),
                    SEO_FORGE_MIN_PHP,
                    PHP_VERSION
                )
            );
        });
        return false;
    }
    
    // Check WordPress version
    if (version_compare($wp_version, SEO_FORGE_MIN_WP, '<')) {
        add_action('admin_notices', function() use ($wp_version) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    esc_html__('SEO-Forge requires WordPress %1$s or higher. You are running WordPress %2$s.', 'seo-forge'),
                    SEO_FORGE_MIN_WP,
                    $wp_version
                )
            );
        });
        return false;
    }
    
    return true;
}

/**
 * Load the complete plugin
 */
function seo_forge_load_plugin() {
    // Load the complete plugin file
    require_once SEO_FORGE_PATH . 'seo-forge-complete.php';
}

/**
 * Plugin activation hook
 */
function seo_forge_activate() {
    if (!seo_forge_check_requirements()) {
        return;
    }
    
    // Load install script
    require_once SEO_FORGE_PATH . 'install.php';
    seo_forge_install();
}

/**
 * Plugin deactivation hook
 */
function seo_forge_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('seo_forge_daily_analytics');
    wp_clear_scheduled_hook('seo_forge_weekly_report');
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'seo_forge_activate');
register_deactivation_hook(__FILE__, 'seo_forge_deactivate');

// Initialize the plugin
if (seo_forge_check_requirements()) {
    add_action('plugins_loaded', 'seo_forge_load_plugin', 10);
}
