<?php
declare(strict_types=1);

namespace SEOForge\Core;

/**
 * Plugin Activator
 * 
 * Handles plugin activation tasks including database setup, default options,
 * capability registration, and initial configuration.
 * 
 * @package SEOForge\Core
 * @since 2.0.0
 */
class Activator {
    
    /**
     * Plugin activation handler
     * 
     * @throws \Exception If activation fails
     */
    public function activate(): void {
        try {
            $this->checkSystemRequirements();
            $this->createDatabaseTables();
            $this->setDefaultOptions();
            $this->createCustomCapabilities();
            $this->scheduleEvents();
            $this->createDirectories();
            $this->flushRewriteRules();
            
            // Log successful activation
            if (function_exists('error_log')) {
                error_log('SEO-Forge plugin activated successfully');
            }
            
        } catch (\Throwable $e) {
            // Clean up on failure
            $this->rollbackActivation();
            throw new \Exception('Plugin activation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Check system requirements
     * 
     * @throws \Exception If requirements not met
     */
    private function checkSystemRequirements(): void {
        global $wp_version;
        
        // Check PHP version
        if (version_compare(PHP_VERSION, \SEOForge\PLUGIN_MIN_PHP, '<')) {
            throw new \Exception(
                sprintf(
                    'PHP %s or higher is required. You are running PHP %s.',
                    \SEOForge\PLUGIN_MIN_PHP,
                    PHP_VERSION
                )
            );
        }
        
        // Check WordPress version
        if (version_compare($wp_version, \SEOForge\PLUGIN_MIN_WP, '<')) {
            throw new \Exception(
                sprintf(
                    'WordPress %s or higher is required. You are running WordPress %s.',
                    \SEOForge\PLUGIN_MIN_WP,
                    $wp_version
                )
            );
        }
        
        // Check required PHP extensions
        $requiredExtensions = ['json', 'curl', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new \Exception("Required PHP extension '{$extension}' is not loaded.");
            }
        }
        
        // Check database connectivity
        global $wpdb;
        if (!$wpdb || $wpdb->last_error) {
            throw new \Exception('Database connection error: ' . ($wpdb->last_error ?? 'Unknown error'));
        }
    }
    
    /**
     * Create database tables
     */
    private function createDatabaseTables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SEO Content table
        $seo_content_table = $wpdb->prefix . 'seo_forge_content';
        $sql_content = "CREATE TABLE IF NOT EXISTS $seo_content_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT NULL,
            content_type varchar(50) NOT NULL DEFAULT 'blog',
            title text NOT NULL,
            content longtext NOT NULL,
            meta_description text,
            keywords text,
            target_audience varchar(100),
            language varchar(10) DEFAULT 'en',
            status varchar(20) DEFAULT 'draft',
            ai_generated tinyint(1) DEFAULT 0,
            quality_score decimal(3,2) DEFAULT NULL,
            seo_score decimal(3,2) DEFAULT NULL,
            readability_score decimal(3,2) DEFAULT NULL,
            word_count int unsigned DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY content_type (content_type),
            KEY status (status),
            KEY language (language),
            KEY created_by (created_by),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // SEO Analytics table
        $analytics_table = $wpdb->prefix . 'seo_forge_analytics';
        $sql_analytics = "CREATE TABLE IF NOT EXISTS $analytics_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned DEFAULT NULL,
            url varchar(255) NOT NULL,
            metric_type varchar(50) NOT NULL,
            metric_value decimal(10,2) NOT NULL,
            date_recorded date NOT NULL,
            source varchar(50) DEFAULT 'internal',
            additional_data longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY url (url),
            KEY metric_type (metric_type),
            KEY date_recorded (date_recorded),
            KEY source (source),
            UNIQUE KEY unique_metric (post_id, metric_type, date_recorded, source)
        ) $charset_collate;";
        
        // SEO Keywords table
        $keywords_table = $wpdb->prefix . 'seo_forge_keywords';
        $sql_keywords = "CREATE TABLE IF NOT EXISTS $keywords_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            search_volume int unsigned DEFAULT NULL,
            competition_level varchar(20) DEFAULT NULL,
            cpc decimal(8,2) DEFAULT NULL,
            difficulty_score decimal(3,2) DEFAULT NULL,
            language varchar(10) DEFAULT 'en',
            country varchar(5) DEFAULT NULL,
            category varchar(100) DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_keyword (keyword, language, country),
            KEY search_volume (search_volume),
            KEY competition_level (competition_level),
            KEY difficulty_score (difficulty_score),
            KEY language (language),
            KEY category (category),
            KEY status (status)
        ) $charset_collate;";
        
        // SEO Templates table
        $templates_table = $wpdb->prefix . 'seo_forge_templates';
        $sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            template_content longtext NOT NULL,
            variables longtext,
            language varchar(10) DEFAULT 'en',
            category varchar(100) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            usage_count int unsigned DEFAULT 0,
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name),
            KEY type (type),
            KEY language (language),
            KEY category (category),
            KEY is_active (is_active),
            KEY created_by (created_by)
        ) $charset_collate;";
        
        // SEO Settings table
        $settings_table = $wpdb->prefix . 'seo_forge_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            setting_type varchar(50) DEFAULT 'string',
            is_encrypted tinyint(1) DEFAULT 0,
            autoload tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_setting (setting_key),
            KEY autoload (autoload)
        ) $charset_collate;";
        
        // Execute table creation
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta($sql_content);
        dbDelta($sql_analytics);
        dbDelta($sql_keywords);
        dbDelta($sql_templates);
        dbDelta($sql_settings);
        
        // Check for errors
        if ($wpdb->last_error) {
            throw new \Exception('Database table creation failed: ' . $wpdb->last_error);
        }
    }
    
    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void {
        $defaultOptions = [
            'seo_forge_version' => \SEOForge\PLUGIN_VERSION,
            'seo_forge_activated_at' => current_time('mysql'),
            'seo_forge_settings' => [
                'general' => [
                    'enable_analytics' => true,
                    'enable_blog_generator' => true,
                    'enable_chatbot' => false,
                    'default_language' => 'en',
                    'content_quality_threshold' => 0.7,
                    'auto_publish' => false,
                ],
                'api' => [
                    'openai_api_key' => '',
                    'google_analytics_id' => '',
                    'google_search_console_id' => '',
                    'rate_limit_requests' => 100,
                    'rate_limit_window' => 3600,
                ],
                'security' => [
                    'enable_rate_limiting' => true,
                    'enable_ip_blocking' => true,
                    'max_login_attempts' => 5,
                    'block_duration' => 3600,
                    'enable_security_headers' => true,
                ],
                'content' => [
                    'default_word_count' => 1000,
                    'enable_auto_tags' => true,
                    'enable_auto_categories' => true,
                    'content_templates' => [],
                ],
                'performance' => [
                    'enable_caching' => true,
                    'cache_duration' => 3600,
                    'enable_compression' => true,
                    'optimize_images' => true,
                ],
            ],
        ];
        
        foreach ($defaultOptions as $option => $value) {
            if (!get_option($option)) {
                add_option($option, $value);
            }
        }
        
        // Set plugin activation flag
        update_option('seo_forge_activation_redirect', true);
    }
    
    /**
     * Create custom capabilities
     */
    private function createCustomCapabilities(): void {
        $capabilities = [
            'manage_seo_forge' => 'Manage SEO Forge',
            'edit_seo_content' => 'Edit SEO Content',
            'publish_seo_content' => 'Publish SEO Content',
            'delete_seo_content' => 'Delete SEO Content',
            'view_seo_analytics' => 'View SEO Analytics',
            'manage_seo_settings' => 'Manage SEO Settings',
            'use_ai_generator' => 'Use AI Content Generator',
        ];
        
        // Add capabilities to administrator role
        $admin_role = get_role('administrator');
        if ($admin_role) {
            foreach ($capabilities as $cap => $description) {
                $admin_role->add_cap($cap);
            }
        }
        
        // Create SEO Manager role
        add_role('seo_manager', 'SEO Manager', [
            'read' => true,
            'manage_seo_forge' => true,
            'edit_seo_content' => true,
            'publish_seo_content' => true,
            'view_seo_analytics' => true,
            'use_ai_generator' => true,
        ]);
        
        // Create SEO Editor role
        add_role('seo_editor', 'SEO Editor', [
            'read' => true,
            'edit_seo_content' => true,
            'view_seo_analytics' => true,
            'use_ai_generator' => true,
        ]);
    }
    
    /**
     * Schedule plugin events
     */
    private function scheduleEvents(): void {
        // Schedule content generation
        if (!wp_next_scheduled('seo_forge_generate_content')) {
            wp_schedule_event(time(), 'hourly', 'seo_forge_generate_content');
        }
        
        // Schedule analytics sync
        if (!wp_next_scheduled('seo_forge_sync_analytics')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_sync_analytics');
        }
        
        // Schedule keyword research
        if (!wp_next_scheduled('seo_forge_keyword_research')) {
            wp_schedule_event(time(), 'weekly', 'seo_forge_keyword_research');
        }
        
        // Schedule cleanup tasks
        if (!wp_next_scheduled('seo_forge_cleanup')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_cleanup');
        }
        
        // Schedule security audit
        if (!wp_next_scheduled('seo_forge_security_audit')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_security_audit');
        }
    }
    
    /**
     * Create necessary directories
     */
    private function createDirectories(): void {
        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'] . '/seo-forge';
        
        $directories = [
            $baseDir,
            $baseDir . '/logs',
            $baseDir . '/cache',
            $baseDir . '/exports',
            $baseDir . '/templates',
            $baseDir . '/backups',
        ];
        
        foreach ($directories as $dir) {
            if (!wp_mkdir_p($dir)) {
                throw new \Exception("Failed to create directory: {$dir}");
            }
            
            // Create .htaccess to protect directories
            $htaccess = $dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
            
            // Create index.php to prevent directory listing
            $index = $dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }
    
    /**
     * Flush rewrite rules
     */
    private function flushRewriteRules(): void {
        // Register custom post types and taxonomies first
        $this->registerCustomPostTypes();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Register custom post types for activation
     */
    private function registerCustomPostTypes(): void {
        // SEO Content post type
        register_post_type('seo_content', [
            'labels' => [
                'name' => __('SEO Content', 'seo-forge'),
                'singular_name' => __('SEO Content', 'seo-forge'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'seo-content'],
        ]);
        
        // Blog Templates post type
        register_post_type('blog_template', [
            'labels' => [
                'name' => __('Blog Templates', 'seo-forge'),
                'singular_name' => __('Blog Template', 'seo-forge'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'blog-template'],
        ]);
    }
    
    /**
     * Rollback activation on failure
     */
    private function rollbackActivation(): void {
        try {
            // Remove scheduled events
            wp_clear_scheduled_hook('seo_forge_generate_content');
            wp_clear_scheduled_hook('seo_forge_sync_analytics');
            wp_clear_scheduled_hook('seo_forge_keyword_research');
            wp_clear_scheduled_hook('seo_forge_cleanup');
            wp_clear_scheduled_hook('seo_forge_security_audit');
            
            // Remove options
            delete_option('seo_forge_version');
            delete_option('seo_forge_activated_at');
            delete_option('seo_forge_settings');
            delete_option('seo_forge_activation_redirect');
            
            // Remove custom roles
            remove_role('seo_manager');
            remove_role('seo_editor');
            
            // Remove capabilities from admin role
            $admin_role = get_role('administrator');
            if ($admin_role) {
                $capabilities = [
                    'manage_seo_forge',
                    'edit_seo_content',
                    'publish_seo_content',
                    'delete_seo_content',
                    'view_seo_analytics',
                    'manage_seo_settings',
                    'use_ai_generator',
                ];
                
                foreach ($capabilities as $cap) {
                    $admin_role->remove_cap($cap);
                }
            }
            
        } catch (\Throwable $e) {
            // Log rollback error but don't throw
            if (function_exists('error_log')) {
                error_log('SEO-Forge activation rollback error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Get activation status
     * 
     * @return array Activation status information
     */
    public static function getActivationStatus(): array {
        return [
            'version' => get_option('seo_forge_version'),
            'activated_at' => get_option('seo_forge_activated_at'),
            'redirect_pending' => get_option('seo_forge_activation_redirect', false),
        ];
    }
}