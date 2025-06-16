<?php
declare(strict_types=1);
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

namespace SEOForge;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Plugin constants
const PLUGIN_VERSION = '2.0.0';
const PLUGIN_MIN_PHP = '8.0';
const PLUGIN_MIN_WP = '6.0';

// Define plugin paths and URLs
define('SEO_FORGE_FILE', __FILE__);
define('SEO_FORGE_PATH', plugin_dir_path(__FILE__));
define('SEO_FORGE_URL', plugin_dir_url(__FILE__));
define('SEO_FORGE_BASENAME', plugin_basename(__FILE__));

/**
 * Check system requirements before loading the plugin
 */
function check_requirements(): bool {
    global $wp_version;
    
    // Check PHP version
    if (version_compare(PHP_VERSION, PLUGIN_MIN_PHP, '<')) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: %1$s: required PHP version, %2$s: current PHP version */
                    esc_html__('SEO-Forge requires PHP %1$s or higher. You are running PHP %2$s.', 'seo-forge'),
                    PLUGIN_MIN_PHP,
                    PHP_VERSION
                )
            );
        });
        return false;
    }
    
    // Check WordPress version
    if (version_compare($wp_version, PLUGIN_MIN_WP, '<')) {
        add_action('admin_notices', function() use ($wp_version) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: %1$s: required WordPress version, %2$s: current WordPress version */
                    esc_html__('SEO-Forge requires WordPress %1$s or higher. You are running WordPress %2$s.', 'seo-forge'),
                    PLUGIN_MIN_WP,
                    $wp_version
                )
            );
        });
        return false;
    }
    
    return true;
}

/**
 * Load Composer autoloader
 */
function load_autoloader(): bool {
    $autoloader = SEO_FORGE_PATH . 'vendor/autoload.php';
    
    if (!file_exists($autoloader)) {
        add_action('admin_notices', function() {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html__('SEO-Forge: Composer autoloader not found. Please run "composer install" in the plugin directory.', 'seo-forge')
            );
        });
        return false;
    }
    
    require_once $autoloader;
    return true;
}

/**
 * Initialize the plugin
 */
function init_plugin(): void {
    try {
        $plugin = Core\Plugin::getInstance();
        $plugin->init();
    } catch (\Throwable $e) {
        // Log the error
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SEO-Forge Plugin Error: ' . $e->getMessage());
        }
        
        // Show admin notice
        add_action('admin_notices', function() use ($e) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: error message */
                    esc_html__('SEO-Forge failed to initialize: %s', 'seo-forge'),
                    esc_html($e->getMessage())
                )
            );
        });
    }
}

/**
 * Plugin activation hook
 */
function activate_plugin(): void {
    if (!check_requirements() || !load_autoloader()) {
        return;
    }
    
    try {
        $activator = new Core\Activator();
        $activator->activate();
    } catch (\Throwable $e) {
        wp_die(
            sprintf(
                /* translators: %s: error message */
                esc_html__('Plugin activation failed: %s', 'seo-forge'),
                esc_html($e->getMessage())
            ),
            esc_html__('Plugin Activation Error', 'seo-forge'),
            ['back_link' => true]
        );
    }
}

/**
 * Plugin deactivation hook
 */
function deactivate_plugin(): void {
    if (!load_autoloader()) {
        return;
    }
    
    try {
        $deactivator = new Core\Deactivator();
        $deactivator->deactivate();
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SEO-Forge Deactivation Error: ' . $e->getMessage());
        }
    }
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate_plugin');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate_plugin');

// Initialize the plugin
if (check_requirements() && load_autoloader()) {
    add_action('plugins_loaded', __NAMESPACE__ . '\\init_plugin', 10);
}
