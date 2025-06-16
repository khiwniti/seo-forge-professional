<?php
/**
 * SEO Forge Professional - Installation Script
 * 
 * @package SEOForge
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

/**
 * Install SEO Forge Professional plugin
 */
function seo_forge_install() {
    global $wpdb;
    
    // Create database tables
    seo_forge_create_tables();
    
    // Set default options
    seo_forge_set_default_options();
    
    // Schedule cron jobs
    seo_forge_schedule_cron_jobs();
    
    // Create upload directories
    seo_forge_create_directories();
    
    // Set plugin version
    update_option('seo_forge_version', SEO_FORGE_VERSION);
    
    // Set installation date
    if (!get_option('seo_forge_install_date')) {
        update_option('seo_forge_install_date', current_time('mysql'));
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Create database tables
 */
function seo_forge_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Analytics table
    $analytics_table = $wpdb->prefix . 'seo_forge_analytics';
    $analytics_sql = "CREATE TABLE $analytics_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        post_id bigint(20) unsigned DEFAULT NULL,
        url varchar(255) NOT NULL,
        page_views bigint(20) unsigned DEFAULT 0,
        unique_views bigint(20) unsigned DEFAULT 0,
        bounce_rate decimal(5,2) DEFAULT 0.00,
        avg_time_on_page int(11) DEFAULT 0,
        date_recorded date NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY url (url),
        KEY date_recorded (date_recorded),
        UNIQUE KEY unique_url_date (url, date_recorded)
    ) $charset_collate;";
    
    // Keywords table
    $keywords_table = $wpdb->prefix . 'seo_forge_keywords';
    $keywords_sql = "CREATE TABLE $keywords_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        keyword varchar(255) NOT NULL,
        target_url varchar(255) NOT NULL,
        search_engine varchar(50) DEFAULT 'google',
        current_position int(11) DEFAULT NULL,
        previous_position int(11) DEFAULT NULL,
        best_position int(11) DEFAULT NULL,
        search_volume int(11) DEFAULT NULL,
        difficulty_score decimal(3,1) DEFAULT NULL,
        last_checked datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY keyword (keyword),
        KEY target_url (target_url),
        KEY search_engine (search_engine),
        KEY last_checked (last_checked),
        UNIQUE KEY unique_keyword_url_engine (keyword, target_url, search_engine)
    ) $charset_collate;";
    
    // Settings table
    $settings_table = $wpdb->prefix . 'seo_forge_settings';
    $settings_sql = "CREATE TABLE $settings_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        setting_name varchar(255) NOT NULL,
        setting_value longtext,
        autoload varchar(20) DEFAULT 'yes',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY setting_name (setting_name),
        KEY autoload (autoload)
    ) $charset_collate;";
    
    // Events table for detailed analytics
    $events_table = $wpdb->prefix . 'seo_forge_events';
    $events_sql = "CREATE TABLE $events_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        post_id bigint(20) unsigned DEFAULT NULL,
        url varchar(255) NOT NULL,
        event_type varchar(50) NOT NULL,
        event_data longtext,
        user_agent varchar(500),
        ip_address varchar(45),
        referrer varchar(255),
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY post_id (post_id),
        KEY url (url),
        KEY event_type (event_type),
        KEY timestamp (timestamp)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($analytics_sql);
    dbDelta($keywords_sql);
    dbDelta($settings_sql);
    dbDelta($events_sql);
}

/**
 * Set default plugin options
 */
function seo_forge_set_default_options() {
    $default_options = [
        'seo_forge_general' => [
            'enable_meta_tags' => true,
            'enable_schema_markup' => true,
            'enable_xml_sitemap' => true,
            'enable_breadcrumbs' => false,
            'enable_social_meta' => true,
            'default_meta_description' => '',
            'default_meta_keywords' => '',
            'separator' => '|',
            'home_title_format' => '%site_name% %separator% %tagline%',
            'post_title_format' => '%title% %separator% %site_name%',
            'page_title_format' => '%title% %separator% %site_name%',
            'category_title_format' => '%term_title% %separator% %site_name%',
            'tag_title_format' => '%term_title% %separator% %site_name%',
            'author_title_format' => '%author_name% %separator% %site_name%',
            'archive_title_format' => '%archive_title% %separator% %site_name%'
        ],
        'seo_forge_analytics' => [
            'enable_internal_tracking' => true,
            'google_analytics_id' => '',
            'google_search_console_id' => '',
            'track_logged_in_users' => false,
            'track_404_errors' => true,
            'anonymize_ip' => true,
            'data_retention_days' => 365
        ],
        'seo_forge_content' => [
            'api_provider' => 'seo-forge',
            'api_endpoint' => 'https://seo-forge.bitebase.app/api',
            'api_key' => '',
            'openai_api_key' => '',
            'openai_model' => 'gpt-3.5-turbo',
            'default_content_length' => 'medium',
            'enable_auto_tags' => true,
            'enable_auto_categories' => false,
            'content_quality_threshold' => 70
        ],
        'seo_forge_keywords' => [
            'enable_keyword_tracking' => true,
            'default_search_engine' => 'google',
            'check_frequency' => 'daily',
            'max_keywords' => 100,
            'enable_serp_features' => true,
            'track_competitors' => false
        ],
        'seo_forge_advanced' => [
            'enable_cache' => true,
            'cache_duration' => 3600,
            'enable_minification' => false,
            'enable_lazy_loading' => false,
            'enable_amp' => false,
            'enable_pwa' => false,
            'custom_css' => '',
            'custom_js' => '',
            'robots_txt_additions' => ''
        ]
    ];
    
    foreach ($default_options as $option_name => $option_value) {
        if (!get_option($option_name)) {
            update_option($option_name, $option_value);
        }
    }
}

/**
 * Schedule cron jobs
 */
function seo_forge_schedule_cron_jobs() {
    // Daily analytics sync
    if (!wp_next_scheduled('seo_forge_daily_analytics')) {
        wp_schedule_event(time(), 'daily', 'seo_forge_daily_analytics');
    }
    
    // Weekly SEO report
    if (!wp_next_scheduled('seo_forge_weekly_report')) {
        wp_schedule_event(time(), 'weekly', 'seo_forge_weekly_report');
    }
    
    // Hourly keyword check
    if (!wp_next_scheduled('seo_forge_keyword_check')) {
        wp_schedule_event(time(), 'hourly', 'seo_forge_keyword_check');
    }
    
    // Daily cleanup
    if (!wp_next_scheduled('seo_forge_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'seo_forge_daily_cleanup');
    }
}

/**
 * Create necessary directories
 */
function seo_forge_create_directories() {
    $upload_dir = wp_upload_dir();
    $seo_forge_dir = $upload_dir['basedir'] . '/seo-forge';
    
    // Create main directory
    if (!file_exists($seo_forge_dir)) {
        wp_mkdir_p($seo_forge_dir);
    }
    
    // Create subdirectories
    $subdirs = ['cache', 'exports', 'logs', 'temp'];
    foreach ($subdirs as $subdir) {
        $dir_path = $seo_forge_dir . '/' . $subdir;
        if (!file_exists($dir_path)) {
            wp_mkdir_p($dir_path);
        }
        
        // Add index.php to prevent directory browsing
        $index_file = $dir_path . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    // Create .htaccess for security
    $htaccess_file = $seo_forge_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "# SEO Forge Security\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<Files *.php>\n";
        $htaccess_content .= "deny from all\n";
        $htaccess_content .= "</Files>\n";
        file_put_contents($htaccess_file, $htaccess_content);
    }
}

/**
 * Create custom capabilities
 */
function seo_forge_create_capabilities() {
    $admin_role = get_role('administrator');
    $editor_role = get_role('editor');
    
    if ($admin_role) {
        $admin_role->add_cap('manage_seo_forge');
        $admin_role->add_cap('edit_seo_content');
        $admin_role->add_cap('view_seo_analytics');
        $admin_role->add_cap('manage_seo_keywords');
    }
    
    if ($editor_role) {
        $editor_role->add_cap('edit_seo_content');
        $editor_role->add_cap('view_seo_analytics');
    }
}

/**
 * Insert sample data for testing
 */
function seo_forge_insert_sample_data() {
    global $wpdb;
    
    // Only insert sample data if tables are empty
    $analytics_table = $wpdb->prefix . 'seo_forge_analytics';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $analytics_table");
    
    if ($count == 0) {
        // Insert sample analytics data
        $sample_data = [
            [
                'url' => home_url('/'),
                'page_views' => 150,
                'unique_views' => 120,
                'bounce_rate' => 45.5,
                'avg_time_on_page' => 180,
                'date_recorded' => date('Y-m-d', strtotime('-1 day'))
            ],
            [
                'url' => home_url('/about/'),
                'page_views' => 85,
                'unique_views' => 70,
                'bounce_rate' => 35.2,
                'avg_time_on_page' => 240,
                'date_recorded' => date('Y-m-d', strtotime('-1 day'))
            ],
            [
                'url' => home_url('/contact/'),
                'page_views' => 45,
                'unique_views' => 40,
                'bounce_rate' => 25.8,
                'avg_time_on_page' => 120,
                'date_recorded' => date('Y-m-d', strtotime('-1 day'))
            ]
        ];
        
        foreach ($sample_data as $data) {
            $wpdb->insert($analytics_table, $data);
        }
    }
}

/**
 * Run installation
 */
if (!function_exists('seo_forge_install')) {
    // This function is already defined above
}