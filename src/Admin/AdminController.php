<?php
declare(strict_types=1);

namespace SEOForge\Admin;

use SEOForge\Services\Logger;
use SEOForge\Security\SecurityManager;
use Psr\Log\LoggerInterface;

/**
 * Admin Controller
 * 
 * Manages all admin-related functionality including menu registration,
 * asset enqueuing, and admin interface components.
 * 
 * @package SEOForge\Admin
 * @since 2.0.0
 */
class AdminController {
    
    /**
     * Logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * Security manager instance
     */
    private SecurityManager $security;
    
    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger, SecurityManager $security) {
        $this->logger = $logger;
        $this->security = $security;
    }
    
    /**
     * Initialize admin controller
     */
    public function init(): void {
        $this->setupHooks();
        $this->handleActivationRedirect();
    }
    
    /**
     * Setup admin hooks
     */
    private function setupHooks(): void {
        // Admin menu hooks
        add_action('admin_menu', [$this, 'registerMenus'], 10);
        add_action('admin_init', [$this, 'registerSettings'], 10);
        
        // Asset hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 10);
        
        // AJAX hooks
        add_action('wp_ajax_seo_forge_save_settings', [$this, 'handleSaveSettings']);
        add_action('wp_ajax_seo_forge_generate_content', [$this, 'handleGenerateContent']);
        add_action('wp_ajax_seo_forge_analyze_content', [$this, 'handleAnalyzeContent']);
        add_action('wp_ajax_seo_forge_export_data', [$this, 'handleExportData']);
        
        // Meta box hooks
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'savePostMeta']);
        
        // Admin notices
        add_action('admin_notices', [$this, 'displayAdminNotices']);
        
        // Dashboard widgets
        add_action('wp_dashboard_setup', [$this, 'addDashboardWidgets']);
        
        // Admin bar
        add_action('admin_bar_menu', [$this, 'addAdminBarItems'], 100);
    }
    
    /**
     * Register admin menus
     */
    public function registerMenus(): void {
        // Main menu
        add_menu_page(
            __('SEO Forge', 'seo-forge'),
            __('SEO Forge', 'seo-forge'),
            'manage_seo_forge',
            'seo-forge',
            [$this, 'renderDashboardPage'],
            'dashicons-chart-line',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'seo-forge',
            __('Dashboard', 'seo-forge'),
            __('Dashboard', 'seo-forge'),
            'manage_seo_forge',
            'seo-forge',
            [$this, 'renderDashboardPage']
        );
        
        // Content Generator submenu
        add_submenu_page(
            'seo-forge',
            __('Content Generator', 'seo-forge'),
            __('Content Generator', 'seo-forge'),
            'use_ai_generator',
            'seo-forge-generator',
            [$this, 'renderGeneratorPage']
        );
        
        // Analytics submenu
        add_submenu_page(
            'seo-forge',
            __('Analytics', 'seo-forge'),
            __('Analytics', 'seo-forge'),
            'view_seo_analytics',
            'seo-forge-analytics',
            [$this, 'renderAnalyticsPage']
        );
        
        // Keywords submenu
        add_submenu_page(
            'seo-forge',
            __('Keywords', 'seo-forge'),
            __('Keywords', 'seo-forge'),
            'edit_seo_content',
            'seo-forge-keywords',
            [$this, 'renderKeywordsPage']
        );
        
        // Templates submenu
        add_submenu_page(
            'seo-forge',
            __('Templates', 'seo-forge'),
            __('Templates', 'seo-forge'),
            'edit_seo_content',
            'seo-forge-templates',
            [$this, 'renderTemplatesPage']
        );
        
        // Settings submenu
        add_submenu_page(
            'seo-forge',
            __('Settings', 'seo-forge'),
            __('Settings', 'seo-forge'),
            'manage_seo_settings',
            'seo-forge-settings',
            [$this, 'renderSettingsPage']
        );
        
        // Tools submenu
        add_submenu_page(
            'seo-forge',
            __('Tools', 'seo-forge'),
            __('Tools', 'seo-forge'),
            'manage_seo_forge',
            'seo-forge-tools',
            [$this, 'renderToolsPage']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function registerSettings(): void {
        // General settings
        register_setting('seo_forge_general', 'seo_forge_settings', [
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);
        
        // API settings
        register_setting('seo_forge_api', 'seo_forge_api_settings', [
            'sanitize_callback' => [$this, 'sanitizeApiSettings'],
        ]);
        
        // Security settings
        register_setting('seo_forge_security', 'seo_forge_security_settings', [
            'sanitize_callback' => [$this, 'sanitizeSecuritySettings'],
        ]);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueueAssets(): void {
        $screen = get_current_screen();
        
        if (!$screen || strpos($screen->id, 'seo-forge') === false) {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'seo-forge-admin',
            SEO_FORGE_URL . 'assets/css/admin.css',
            [],
            \SEOForge\PLUGIN_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'seo-forge-admin',
            SEO_FORGE_URL . 'assets/js/admin.js',
            ['jquery', 'wp-api-fetch'],
            \SEOForge\PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('seo-forge-admin', 'seoForgeAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('seo-forge/v1/'),
            'nonce' => $this->security->generateCsrfToken('admin'),
            'strings' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'seo-forge'),
                'saving' => __('Saving...', 'seo-forge'),
                'saved' => __('Saved!', 'seo-forge'),
                'error' => __('An error occurred. Please try again.', 'seo-forge'),
            ],
        ]);
        
        // Enqueue WordPress media library
        wp_enqueue_media();
        
        // Enqueue code editor for templates
        if ($screen->id === 'seo-forge_page_seo-forge-templates') {
            wp_enqueue_code_editor(['type' => 'text/html']);
        }
    }
    
    /**
     * Add meta boxes
     */
    public function addMetaBoxes(): void {
        $post_types = ['post', 'page'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'seo-forge-meta',
                __('SEO Forge', 'seo-forge'),
                [$this, 'renderMetaBox'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    /**
     * Render meta box
     */
    public function renderMetaBox(\WP_Post $post): void {
        // Add nonce field
        wp_nonce_field('seo_forge_meta_box', 'seo_forge_meta_nonce');
        
        // Get existing meta values
        $meta_description = get_post_meta($post->ID, '_seo_forge_meta_description', true);
        $focus_keyword = get_post_meta($post->ID, '_seo_forge_focus_keyword', true);
        $seo_score = get_post_meta($post->ID, '_seo_forge_seo_score', true);
        
        include SEO_FORGE_PATH . 'templates/admin/meta-box.php';
    }
    
    /**
     * Save post meta
     */
    public function savePostMeta(int $post_id): void {
        // Verify nonce
        if (!isset($_POST['seo_forge_meta_nonce']) || 
            !wp_verify_nonce($_POST['seo_forge_meta_nonce'], 'seo_forge_meta_box')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save meta description
        if (isset($_POST['seo_forge_meta_description'])) {
            update_post_meta(
                $post_id,
                '_seo_forge_meta_description',
                $this->security->sanitizeInput($_POST['seo_forge_meta_description'], 'textarea')
            );
        }
        
        // Save focus keyword
        if (isset($_POST['seo_forge_focus_keyword'])) {
            update_post_meta(
                $post_id,
                '_seo_forge_focus_keyword',
                $this->security->sanitizeInput($_POST['seo_forge_focus_keyword'], 'text')
            );
        }
    }
    
    /**
     * Display admin notices
     */
    public function displayAdminNotices(): void {
        // Success messages
        if (isset($_GET['seo-forge-message'])) {
            $message = sanitize_text_field($_GET['seo-forge-message']);
            $messages = [
                'settings-saved' => __('Settings saved successfully.', 'seo-forge'),
                'content-generated' => __('Content generated successfully.', 'seo-forge'),
                'data-exported' => __('Data exported successfully.', 'seo-forge'),
            ];
            
            if (isset($messages[$message])) {
                printf(
                    '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                    esc_html($messages[$message])
                );
            }
        }
        
        // Error messages
        if (isset($_GET['seo-forge-error'])) {
            $error = sanitize_text_field($_GET['seo-forge-error']);
            $errors = [
                'invalid-api-key' => __('Invalid API key. Please check your settings.', 'seo-forge'),
                'generation-failed' => __('Content generation failed. Please try again.', 'seo-forge'),
                'export-failed' => __('Data export failed. Please try again.', 'seo-forge'),
            ];
            
            if (isset($errors[$error])) {
                printf(
                    '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                    esc_html($errors[$error])
                );
            }
        }
    }
    
    /**
     * Add dashboard widgets
     */
    public function addDashboardWidgets(): void {
        if (current_user_can('view_seo_analytics')) {
            wp_add_dashboard_widget(
                'seo-forge-overview',
                __('SEO Forge Overview', 'seo-forge'),
                [$this, 'renderDashboardWidget']
            );
        }
    }
    
    /**
     * Render dashboard widget
     */
    public function renderDashboardWidget(): void {
        include SEO_FORGE_PATH . 'templates/admin/dashboard-widget.php';
    }
    
    /**
     * Add admin bar items
     */
    public function addAdminBarItems(\WP_Admin_Bar $admin_bar): void {
        if (!current_user_can('manage_seo_forge')) {
            return;
        }
        
        $admin_bar->add_menu([
            'id' => 'seo-forge',
            'title' => __('SEO Forge', 'seo-forge'),
            'href' => admin_url('admin.php?page=seo-forge'),
        ]);
        
        $admin_bar->add_menu([
            'id' => 'seo-forge-generator',
            'parent' => 'seo-forge',
            'title' => __('Generate Content', 'seo-forge'),
            'href' => admin_url('admin.php?page=seo-forge-generator'),
        ]);
        
        $admin_bar->add_menu([
            'id' => 'seo-forge-analytics',
            'parent' => 'seo-forge',
            'title' => __('Analytics', 'seo-forge'),
            'href' => admin_url('admin.php?page=seo-forge-analytics'),
        ]);
    }
    
    /**
     * Handle activation redirect
     */
    private function handleActivationRedirect(): void {
        if (get_option('seo_forge_activation_redirect', false)) {
            delete_option('seo_forge_activation_redirect');
            
            if (!isset($_GET['activate-multi'])) {
                wp_redirect(admin_url('admin.php?page=seo-forge&welcome=1'));
                exit;
            }
        }
    }
    
    /**
     * Render dashboard page
     */
    public function renderDashboardPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Render generator page
     */
    public function renderGeneratorPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/generator.php';
    }
    
    /**
     * Render analytics page
     */
    public function renderAnalyticsPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/analytics.php';
    }
    
    /**
     * Render keywords page
     */
    public function renderKeywordsPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/keywords.php';
    }
    
    /**
     * Render templates page
     */
    public function renderTemplatesPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/templates.php';
    }
    
    /**
     * Render settings page
     */
    public function renderSettingsPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/settings.php';
    }
    
    /**
     * Render tools page
     */
    public function renderToolsPage(): void {
        include SEO_FORGE_PATH . 'templates/admin/tools.php';
    }
    
    /**
     * Handle save settings AJAX
     */
    public function handleSaveSettings(): void {
        $this->security->verifyCsrfToken();
        
        if (!current_user_can('manage_seo_settings')) {
            wp_die(__('Insufficient permissions.', 'seo-forge'), 403);
        }
        
        $settings = $_POST['settings'] ?? [];
        $sanitized = $this->sanitizeSettings($settings);
        
        update_option('seo_forge_settings', $sanitized);
        
        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'seo-forge'),
        ]);
    }
    
    /**
     * Handle generate content AJAX
     */
    public function handleGenerateContent(): void {
        $this->security->verifyCsrfToken();
        
        if (!current_user_can('use_ai_generator')) {
            wp_die(__('Insufficient permissions.', 'seo-forge'), 403);
        }
        
        // Content generation logic will be implemented in BlogGenerator service
        do_action('seo_forge_generate_content_ajax', $_POST);
        
        wp_send_json_success([
            'message' => __('Content generation started.', 'seo-forge'),
        ]);
    }
    
    /**
     * Handle analyze content AJAX
     */
    public function handleAnalyzeContent(): void {
        $this->security->verifyCsrfToken();
        
        if (!current_user_can('edit_seo_content')) {
            wp_die(__('Insufficient permissions.', 'seo-forge'), 403);
        }
        
        $content = $_POST['content'] ?? '';
        $keyword = $_POST['keyword'] ?? '';
        
        // Content analysis logic will be implemented
        $analysis = $this->analyzeContent($content, $keyword);
        
        wp_send_json_success($analysis);
    }
    
    /**
     * Handle export data AJAX
     */
    public function handleExportData(): void {
        $this->security->verifyCsrfToken();
        
        if (!current_user_can('manage_seo_forge')) {
            wp_die(__('Insufficient permissions.', 'seo-forge'), 403);
        }
        
        $export_type = $_POST['export_type'] ?? 'all';
        
        // Export logic will be implemented
        $export_url = $this->exportData($export_type);
        
        wp_send_json_success([
            'download_url' => $export_url,
            'message' => __('Export completed successfully.', 'seo-forge'),
        ]);
    }
    
    /**
     * Sanitize settings
     */
    public function sanitizeSettings(array $settings): array {
        // Implementation will be added based on specific settings structure
        return $settings;
    }
    
    /**
     * Sanitize API settings
     */
    public function sanitizeApiSettings(array $settings): array {
        // Implementation will be added based on API settings structure
        return $settings;
    }
    
    /**
     * Sanitize security settings
     */
    public function sanitizeSecuritySettings(array $settings): array {
        // Implementation will be added based on security settings structure
        return $settings;
    }
    
    /**
     * Analyze content
     */
    private function analyzeContent(string $content, string $keyword): array {
        // Placeholder for content analysis logic
        return [
            'seo_score' => 75,
            'readability_score' => 80,
            'keyword_density' => 2.5,
            'suggestions' => [],
        ];
    }
    
    /**
     * Export data
     */
    private function exportData(string $type): string {
        // Placeholder for export logic
        return admin_url('admin.php?page=seo-forge-tools&action=download&file=export.csv');
    }
}