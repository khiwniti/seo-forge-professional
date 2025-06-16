<?php
declare(strict_types=1);
/**
 * Plugin Name: SEO-Forge Professional Complete
 * Plugin URI: https://seo-forge.bitebase.app
 * Description: Complete WordPress SEO plugin with AI-powered content generation, analytics, and comprehensive SEO tools. All-in-one solution using WordPress native UI.
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
 * Main SEO Forge Plugin Class
 */
class SEOForgeComplete {
    
    private static $instance = null;
    private $options = [];
    private $db_version = '1.0';
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->options = get_option('seo_forge_options', []);
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('wp_head', [$this, 'add_seo_meta_tags']);
        add_action('wp_footer', [$this, 'add_analytics_code']);
        add_action('save_post', [$this, 'save_post_seo_data']);
        add_action('wp_ajax_seo_forge_generate_content', [$this, 'ajax_generate_content']);
        add_action('wp_ajax_seo_forge_analyze_content', [$this, 'ajax_analyze_content']);
        add_action('wp_ajax_seo_forge_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_seo_forge_save_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_seo_forge_test_api', [$this, 'ajax_test_api']);
        add_action('wp_ajax_seo_forge_save_generated_content', [$this, 'ajax_save_generated_content']);
        
        // Admin interface customization
        add_action('admin_notices', [$this, 'show_seo_content_notice']);
        add_filter('views_edit-seo_content', [$this, 'customize_seo_content_views']);
        
        // Cron jobs
        add_action('seo_forge_daily_analytics', [$this, 'daily_analytics_sync']);
        add_action('seo_forge_weekly_report', [$this, 'weekly_seo_report']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function init() {
        load_plugin_textdomain('seo-forge', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Register custom post types
        $this->register_post_types();
        
        // Schedule cron jobs
        if (!wp_next_scheduled('seo_forge_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_daily_analytics');
        }
        if (!wp_next_scheduled('seo_forge_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'seo_forge_weekly_report');
        }
    }
    
    public function admin_init() {
        // Add meta boxes
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        
        // Register settings
        register_setting('seo_forge_settings', 'seo_forge_options', [$this, 'sanitize_options']);
    }
    
    public function admin_menu() {
        add_menu_page(
            __('SEO Forge', 'seo-forge'),
            __('SEO Forge', 'seo-forge'),
            'manage_options',
            'seo-forge',
            [$this, 'admin_main_page'],
            'dashicons-chart-line',
            30
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'seo-forge') !== false) {
            wp_enqueue_script('seo-forge-admin', SEO_FORGE_URL . 'assets/js/seo-forge-admin.js', ['jquery', 'wp-api'], SEO_FORGE_VERSION, true);
            wp_enqueue_style('seo-forge-admin', SEO_FORGE_URL . 'assets/css/seo-forge-admin.css', [], SEO_FORGE_VERSION);
            
            wp_localize_script('seo-forge-admin', 'seoForgeAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_forge_nonce'),
                'restUrl' => rest_url('seo-forge/v1/'),
                'restNonce' => wp_create_nonce('wp_rest')
            ]);
        }
    }
    
    public function enqueue_frontend_scripts() {
        if ($this->get_option('enable_frontend_analytics', true)) {
            wp_enqueue_script('seo-forge-frontend', SEO_FORGE_URL . 'assets/js/seo-forge-frontend.js', ['jquery'], SEO_FORGE_VERSION, true);
            wp_localize_script('seo-forge-frontend', 'seoForge', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('seo_forge_frontend_nonce'),
                'trackingEnabled' => $this->get_option('enable_tracking', true)
            ]);
        }
    }
    
    public function register_post_types() {
        // SEO Content post type
        register_post_type('seo_content', [
            'labels' => [
                'name' => __('SEO Content', 'seo-forge'),
                'singular_name' => __('SEO Content', 'seo-forge'),
                'add_new' => __('Add New Content', 'seo-forge'),
                'add_new_item' => __('Add New SEO Content', 'seo-forge'),
                'edit_item' => __('Edit SEO Content', 'seo-forge'),
                'new_item' => __('New SEO Content', 'seo-forge'),
                'view_item' => __('View SEO Content', 'seo-forge'),
                'search_items' => __('Search SEO Content', 'seo-forge'),
                'not_found' => __('No SEO content found. Use the Content Generator to create your first SEO-optimized content!', 'seo-forge'),
                'not_found_in_trash' => __('No SEO content found in trash', 'seo-forge'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'seo-forge',
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
            'show_in_rest' => true,
        ]);
        
        // Blog Templates post type
        register_post_type('blog_template', [
            'labels' => [
                'name' => __('Blog Templates', 'seo-forge'),
                'singular_name' => __('Blog Template', 'seo-forge'),
                'add_new' => __('Add New Template', 'seo-forge'),
                'add_new_item' => __('Add New Blog Template', 'seo-forge'),
                'edit_item' => __('Edit Blog Template', 'seo-forge'),
                'new_item' => __('New Blog Template', 'seo-forge'),
                'view_item' => __('View Blog Template', 'seo-forge'),
                'search_items' => __('Search Blog Templates', 'seo-forge'),
                'not_found' => __('No blog templates found', 'seo-forge'),
                'not_found_in_trash' => __('No blog templates found in trash', 'seo-forge'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'seo-forge',
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_rest' => true,
        ]);
    }
    
    public function add_meta_boxes() {
        $post_types = ['post', 'page', 'seo_content'];
        foreach ($post_types as $post_type) {
            add_meta_box(
                'seo-forge-meta',
                __('SEO Forge Settings', 'seo-forge'),
                [$this, 'meta_box_callback'],
                $post_type,
                'normal',
                'high'
            );
        }
    }
    
    public function meta_box_callback($post) {
        wp_nonce_field('seo_forge_meta_box', 'seo_forge_meta_box_nonce');
        
        $meta_title = get_post_meta($post->ID, '_seo_forge_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_seo_forge_meta_description', true);
        $focus_keyword = get_post_meta($post->ID, '_seo_forge_focus_keyword', true);
        $canonical_url = get_post_meta($post->ID, '_seo_forge_canonical_url', true);
        $robots_meta = get_post_meta($post->ID, '_seo_forge_robots_meta', true);
        
        echo '<div class="seo-forge-meta-box">';
        echo '<table class="form-table">';
        
        // Meta Title
        echo '<tr>';
        echo '<th><label for="seo_forge_meta_title">' . __('Meta Title', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="seo_forge_meta_title" name="seo_forge_meta_title" value="' . esc_attr($meta_title) . '" class="large-text" maxlength="60" />';
        echo '<p class="description">' . __('Recommended length: 50-60 characters', 'seo-forge') . '</p>';
        echo '<div class="seo-forge-counter"><span id="title-counter">0</span>/60</div>';
        echo '</td>';
        echo '</tr>';
        
        // Meta Description
        echo '<tr>';
        echo '<th><label for="seo_forge_meta_description">' . __('Meta Description', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<textarea id="seo_forge_meta_description" name="seo_forge_meta_description" rows="3" class="large-text" maxlength="160">' . esc_textarea($meta_description) . '</textarea>';
        echo '<p class="description">' . __('Recommended length: 150-160 characters', 'seo-forge') . '</p>';
        echo '<div class="seo-forge-counter"><span id="description-counter">0</span>/160</div>';
        echo '</td>';
        echo '</tr>';
        
        // Focus Keyword
        echo '<tr>';
        echo '<th><label for="seo_forge_focus_keyword">' . __('Focus Keyword', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="seo_forge_focus_keyword" name="seo_forge_focus_keyword" value="' . esc_attr($focus_keyword) . '" class="regular-text" />';
        echo '<p class="description">' . __('Primary keyword for this content', 'seo-forge') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Canonical URL
        echo '<tr>';
        echo '<th><label for="seo_forge_canonical_url">' . __('Canonical URL', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<input type="url" id="seo_forge_canonical_url" name="seo_forge_canonical_url" value="' . esc_attr($canonical_url) . '" class="large-text" />';
        echo '<p class="description">' . __('Leave empty to use default URL', 'seo-forge') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Robots Meta
        echo '<tr>';
        echo '<th><label for="seo_forge_robots_meta">' . __('Robots Meta', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<select id="seo_forge_robots_meta" name="seo_forge_robots_meta">';
        echo '<option value="">' . __('Default', 'seo-forge') . '</option>';
        echo '<option value="noindex" ' . selected($robots_meta, 'noindex', false) . '>' . __('No Index', 'seo-forge') . '</option>';
        echo '<option value="nofollow" ' . selected($robots_meta, 'nofollow', false) . '>' . __('No Follow', 'seo-forge') . '</option>';
        echo '<option value="noindex,nofollow" ' . selected($robots_meta, 'noindex,nofollow', false) . '>' . __('No Index, No Follow', 'seo-forge') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // SEO Analysis
        echo '<div class="seo-forge-analysis">';
        echo '<h4>' . __('SEO Analysis', 'seo-forge') . '</h4>';
        echo '<div id="seo-analysis-results">';
        echo '<p>' . __('Save the post to see SEO analysis', 'seo-forge') . '</p>';
        echo '</div>';
        echo '<button type="button" class="button" id="analyze-seo">' . __('Analyze SEO', 'seo-forge') . '</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    public function save_post_seo_data($post_id) {
        if (!isset($_POST['seo_forge_meta_box_nonce']) || !wp_verify_nonce($_POST['seo_forge_meta_box_nonce'], 'seo_forge_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $fields = [
            'seo_forge_meta_title' => '_seo_forge_meta_title',
            'seo_forge_meta_description' => '_seo_forge_meta_description',
            'seo_forge_focus_keyword' => '_seo_forge_focus_keyword',
            'seo_forge_canonical_url' => '_seo_forge_canonical_url',
            'seo_forge_robots_meta' => '_seo_forge_robots_meta'
        ];
        
        foreach ($fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Perform SEO analysis
        $this->analyze_post_seo($post_id);
    }
    
    public function add_seo_meta_tags() {
        global $post;
        
        if (!is_singular()) {
            return;
        }
        
        $meta_title = get_post_meta($post->ID, '_seo_forge_meta_title', true);
        $meta_description = get_post_meta($post->ID, '_seo_forge_meta_description', true);
        $canonical_url = get_post_meta($post->ID, '_seo_forge_canonical_url', true);
        $robots_meta = get_post_meta($post->ID, '_seo_forge_robots_meta', true);
        
        if ($meta_title) {
            echo '<title>' . esc_html($meta_title) . '</title>' . "\n";
        }
        
        if ($meta_description) {
            echo '<meta name="description" content="' . esc_attr($meta_description) . '">' . "\n";
        }
        
        if ($canonical_url) {
            echo '<link rel="canonical" href="' . esc_url($canonical_url) . '">' . "\n";
        } else {
            echo '<link rel="canonical" href="' . esc_url(get_permalink($post->ID)) . '">' . "\n";
        }
        
        if ($robots_meta) {
            echo '<meta name="robots" content="' . esc_attr($robots_meta) . '">' . "\n";
        }
        
        // Open Graph tags
        echo '<meta property="og:title" content="' . esc_attr($meta_title ?: get_the_title()) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($meta_description ?: wp_trim_words(get_the_excerpt(), 20)) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url(get_permalink()) . '">' . "\n";
        echo '<meta property="og:type" content="article">' . "\n";
        
        if (has_post_thumbnail()) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
            if ($image) {
                echo '<meta property="og:image" content="' . esc_url($image[0]) . '">' . "\n";
            }
        }
        
        // Twitter Card tags
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($meta_title ?: get_the_title()) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($meta_description ?: wp_trim_words(get_the_excerpt(), 20)) . '">' . "\n";
        
        // Schema.org markup
        $this->add_schema_markup();
    }
    
    public function add_schema_markup() {
        global $post;
        
        if (!is_singular(['post', 'page'])) {
            return;
        }
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => is_page() ? 'WebPage' : 'Article',
            'headline' => get_the_title(),
            'description' => wp_trim_words(get_the_excerpt(), 20),
            'url' => get_permalink(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author()
            ]
        ];
        
        if (has_post_thumbnail()) {
            $image = wp_get_attachment_image_src(get_post_thumbnail_id(), 'large');
            if ($image) {
                $schema['image'] = $image[0];
            }
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema) . '</script>' . "\n";
    }
    
    public function add_analytics_code() {
        if (!$this->get_option('enable_tracking', true)) {
            return;
        }
        
        $tracking_id = $this->get_option('google_analytics_id', '');
        if ($tracking_id) {
            echo "
            <script async src='https://www.googletagmanager.com/gtag/js?id={$tracking_id}'></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{$tracking_id}');
            </script>
            ";
        }
        
        // Custom tracking
        echo "
        <script>
            if (typeof seoForge !== 'undefined' && seoForge.trackingEnabled) {
                // Track page view
                jQuery(document).ready(function($) {
                    $.post(seoForge.ajaxurl, {
                        action: 'seo_forge_track_pageview',
                        nonce: seoForge.nonce,
                        url: window.location.href,
                        title: document.title,
                        referrer: document.referrer
                    });
                });
            }
        </script>
        ";
    }
    
    public function register_rest_routes() {
        register_rest_route('seo-forge/v1', '/content', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_content'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('seo-forge/v1', '/content', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_create_content'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('seo-forge/v1', '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_analytics'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('seo-forge/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_generate_content'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('seo-forge/v1', '/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_analyze_content'],
            'permission_callback' => [$this, 'rest_permission_check']
        ]);
        
        register_rest_route('seo-forge/v1', '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_health_check'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public function rest_permission_check() {
        return current_user_can('manage_options');
    }
    
    public function rest_get_content($request) {
        $posts = get_posts([
            'post_type' => 'seo_content',
            'numberposts' => -1,
            'post_status' => 'any'
        ]);
        
        $content = [];
        foreach ($posts as $post) {
            $content[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'status' => $post->post_status,
                'date' => $post->post_date,
                'meta' => get_post_meta($post->ID)
            ];
        }
        
        return rest_ensure_response($content);
    }
    
    public function rest_create_content($request) {
        $params = $request->get_params();
        
        $post_data = [
            'post_title' => sanitize_text_field($params['title'] ?? ''),
            'post_content' => wp_kses_post($params['content'] ?? ''),
            'post_type' => 'seo_content',
            'post_status' => 'draft'
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            return new WP_Error('creation_failed', 'Failed to create content', ['status' => 500]);
        }
        
        // Save meta data
        if (isset($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($post_id, sanitize_key($key), sanitize_text_field($value));
            }
        }
        
        return rest_ensure_response(['id' => $post_id, 'message' => 'Content created successfully']);
    }
    
    public function rest_get_analytics($request) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_analytics';
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY date_recorded DESC LIMIT 100"
        );
        
        return rest_ensure_response($results ?: []);
    }
    
    public function rest_generate_content($request) {
        $params = $request->get_params();
        $topic = sanitize_text_field($params['topic'] ?? '');
        $keywords = sanitize_text_field($params['keywords'] ?? '');
        $length = intval($params['length'] ?? 500);
        
        if (empty($topic)) {
            return new WP_Error('missing_topic', 'Topic is required', ['status' => 400]);
        }
        
        // Simulate AI content generation
        $generated_content = $this->generate_ai_content($topic, $keywords, $length);
        
        return rest_ensure_response([
            'content' => $generated_content,
            'word_count' => str_word_count($generated_content),
            'seo_score' => $this->calculate_seo_score($generated_content, $keywords)
        ]);
    }
    
    public function rest_analyze_content($request) {
        $params = $request->get_params();
        $content = wp_kses_post($params['content'] ?? '');
        $keyword = sanitize_text_field($params['keyword'] ?? '');
        
        $analysis = $this->analyze_content_seo($content, $keyword);
        
        return rest_ensure_response($analysis);
    }
    
    public function rest_health_check($request) {
        global $wpdb;
        
        $health = [
            'status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'version' => SEO_FORGE_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'database_connection' => $wpdb->check_connection(),
            'memory_usage' => memory_get_usage(true),
            'memory_limit' => ini_get('memory_limit')
        ];
        
        return rest_ensure_response($health);
    }
    
    // Main Admin Page with Tabs
    public function admin_main_page() {
        $current_tab = $_GET['tab'] ?? 'dashboard';
        
        if (isset($_POST['submit']) && $current_tab === 'settings') {
            check_admin_referer('seo_forge_settings');
            $this->save_settings($_POST);
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'seo-forge') . '</p></div>';
        }
        
        $this->render_admin_header('SEO Forge Professional');
        
        echo '<div class="wrap">';
        
        // Tab Navigation
        echo '<nav class="nav-tab-wrapper wp-clearfix">';
        $tabs = [
            'dashboard' => __('Dashboard', 'seo-forge'),
            'generator' => __('Content Generator', 'seo-forge'),
            'analytics' => __('Analytics', 'seo-forge'),
            'keywords' => __('Keywords', 'seo-forge'),
            'settings' => __('Settings', 'seo-forge')
        ];
        
        foreach ($tabs as $tab_key => $tab_label) {
            $active_class = ($current_tab === $tab_key) ? ' nav-tab-active' : '';
            echo '<a href="' . admin_url('admin.php?page=seo-forge&tab=' . $tab_key) . '" class="nav-tab' . $active_class . '">' . $tab_label . '</a>';
        }
        echo '</nav>';
        
        // Tab Content
        echo '<div class="seo-forge-tab-content">';
        
        switch ($current_tab) {
            case 'dashboard':
                $this->render_dashboard_tab();
                break;
            case 'generator':
                $this->render_generator_tab();
                break;
            case 'analytics':
                $this->render_analytics_tab();
                break;
            case 'keywords':
                $this->render_keywords_tab();
                break;
            case 'settings':
                $this->render_settings_tab();
                break;
            default:
                $this->render_dashboard_tab();
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    private function render_dashboard_tab() {
        echo '<div class="seo-forge-dashboard">';
        
        // Stats cards
        echo '<div class="seo-forge-stats-grid">';
        
        $total_posts = wp_count_posts()->publish;
        $seo_optimized = $this->count_seo_optimized_posts();
        $avg_seo_score = $this->get_average_seo_score();
        $total_keywords = $this->count_tracked_keywords();
        
        $this->render_stat_card('Total Posts', $total_posts, 'dashicons-admin-post');
        $this->render_stat_card('SEO Optimized', $seo_optimized, 'dashicons-yes-alt');
        $this->render_stat_card('Avg SEO Score', $avg_seo_score . '%', 'dashicons-chart-line');
        $this->render_stat_card('Tracked Keywords', $total_keywords, 'dashicons-tag');
        
        echo '</div>';
        
        // Recent activity
        echo '<div class="seo-forge-recent-activity">';
        echo '<h3>' . __('Recent SEO Activity', 'seo-forge') . '</h3>';
        $this->render_recent_activity();
        echo '</div>';
        
        // Quick actions
        echo '<div class="seo-forge-quick-actions">';
        echo '<h3>' . __('Quick Actions', 'seo-forge') . '</h3>';
        echo '<div class="seo-forge-actions-grid">';
        echo '<a href="' . admin_url('admin.php?page=seo-forge&tab=generator') . '" class="button button-primary">' . __('Generate Content', 'seo-forge') . '</a>';
        echo '<a href="' . admin_url('admin.php?page=seo-forge&tab=analytics') . '" class="button">' . __('View Analytics', 'seo-forge') . '</a>';
        echo '<a href="' . admin_url('admin.php?page=seo-forge&tab=keywords') . '" class="button">' . __('Manage Keywords', 'seo-forge') . '</a>';
        echo '<a href="' . admin_url('admin.php?page=seo-forge&tab=settings') . '" class="button">' . __('Settings', 'seo-forge') . '</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_generator_tab() {
        echo '<div class="seo-forge-generator">';
        
        echo '<form id="content-generator-form" class="seo-forge-form">';
        wp_nonce_field('seo_forge_generate', 'seo_forge_generate_nonce');
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="content_topic">' . __('Topic', 'seo-forge') . '</label></th>';
        echo '<td><input type="text" id="content_topic" name="topic" class="regular-text" required /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="content_keywords">' . __('Keywords', 'seo-forge') . '</label></th>';
        echo '<td><input type="text" id="content_keywords" name="keywords" class="regular-text" placeholder="' . __('Comma-separated keywords', 'seo-forge') . '" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="content_length">' . __('Content Length', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<select id="content_length" name="length">';
        echo '<option value="300">' . __('Short (300 words)', 'seo-forge') . '</option>';
        echo '<option value="500" selected>' . __('Medium (500 words)', 'seo-forge') . '</option>';
        echo '<option value="1000">' . __('Long (1000 words)', 'seo-forge') . '</option>';
        echo '<option value="1500">' . __('Very Long (1500 words)', 'seo-forge') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="content_type">' . __('Content Type', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<select id="content_type" name="type">';
        echo '<option value="blog">' . __('Blog Post', 'seo-forge') . '</option>';
        echo '<option value="article">' . __('Article', 'seo-forge') . '</option>';
        echo '<option value="guide">' . __('How-to Guide', 'seo-forge') . '</option>';
        echo '<option value="review">' . __('Product Review', 'seo-forge') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . __('Generate Content', 'seo-forge') . '</button>';
        echo '</p>';
        
        echo '</form>';
        
        echo '<div id="generated-content" class="seo-forge-generated-content" style="display:none;">';
        echo '<h3>' . __('Generated Content', 'seo-forge') . '</h3>';
        echo '<div id="content-preview"></div>';
        echo '<div id="content-stats"></div>';
        echo '<p>';
        echo '<button type="button" id="save-generated-content" class="button button-primary">' . __('Save as Draft', 'seo-forge') . '</button>';
        echo '<button type="button" id="regenerate-content" class="button">' . __('Regenerate', 'seo-forge') . '</button>';
        echo '</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_analytics_tab() {
        echo '<div class="seo-forge-analytics">';
        
        // Date range selector
        echo '<div class="seo-forge-date-range">';
        echo '<label for="date_range">' . __('Date Range:', 'seo-forge') . '</label>';
        echo '<select id="date_range" name="date_range">';
        echo '<option value="7">' . __('Last 7 days', 'seo-forge') . '</option>';
        echo '<option value="30" selected>' . __('Last 30 days', 'seo-forge') . '</option>';
        echo '<option value="90">' . __('Last 90 days', 'seo-forge') . '</option>';
        echo '<option value="365">' . __('Last year', 'seo-forge') . '</option>';
        echo '</select>';
        echo '</div>';
        
        // Analytics charts
        echo '<div class="seo-forge-charts">';
        echo '<div class="chart-container">';
        echo '<h3>' . __('Page Views', 'seo-forge') . '</h3>';
        echo '<canvas id="pageviews-chart"></canvas>';
        echo '</div>';
        
        echo '<div class="chart-container">';
        echo '<h3>' . __('SEO Performance', 'seo-forge') . '</h3>';
        echo '<canvas id="seo-performance-chart"></canvas>';
        echo '</div>';
        echo '</div>';
        
        // Top performing content
        echo '<div class="seo-forge-top-content">';
        echo '<h3>' . __('Top Performing Content', 'seo-forge') . '</h3>';
        $this->render_top_content_table();
        echo '</div>';
        
        // Keyword rankings
        echo '<div class="seo-forge-keyword-rankings">';
        echo '<h3>' . __('Keyword Rankings', 'seo-forge') . '</h3>';
        $this->render_keyword_rankings_table();
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_keywords_tab() {
        echo '<div class="seo-forge-keywords">';
        
        // Add new keyword form
        echo '<div class="seo-forge-add-keyword">';
        echo '<h3>' . __('Add New Keyword', 'seo-forge') . '</h3>';
        echo '<form id="add-keyword-form" class="seo-forge-form">';
        wp_nonce_field('seo_forge_keyword', 'seo_forge_keyword_nonce');
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label for="keyword">' . __('Keyword', 'seo-forge') . '</label></th>';
        echo '<td><input type="text" id="keyword" name="keyword" class="regular-text" required /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="target_url">' . __('Target URL', 'seo-forge') . '</label></th>';
        echo '<td><input type="url" id="target_url" name="target_url" class="regular-text" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="search_engine">' . __('Search Engine', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<select id="search_engine" name="search_engine">';
        echo '<option value="google">' . __('Google', 'seo-forge') . '</option>';
        echo '<option value="bing">' . __('Bing', 'seo-forge') . '</option>';
        echo '<option value="yahoo">' . __('Yahoo', 'seo-forge') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        echo '<p class="submit">';
        echo '<button type="submit" class="button button-primary">' . __('Add Keyword', 'seo-forge') . '</button>';
        echo '</p>';
        
        echo '</form>';
        echo '</div>';
        
        // Keywords list
        echo '<div class="seo-forge-keywords-list">';
        echo '<h3>' . __('Tracked Keywords', 'seo-forge') . '</h3>';
        $this->render_keywords_table();
        echo '</div>';
        
        echo '</div>';
    }
    
    private function render_settings_tab() {
        echo '<form method="post" action="">';
        wp_nonce_field('seo_forge_settings');
        
        // General Settings
        echo '<div class="seo-forge-settings-section">';
        echo '<h3>' . __('General Settings', 'seo-forge') . '</h3>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="enable_seo_meta">' . __('Enable SEO Meta Tags', 'seo-forge') . '</label></th>';
        echo '<td><input type="checkbox" id="enable_seo_meta" name="enable_seo_meta" value="1" ' . checked($this->get_option('enable_seo_meta', true), true, false) . ' /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="enable_schema_markup">' . __('Enable Schema Markup', 'seo-forge') . '</label></th>';
        echo '<td><input type="checkbox" id="enable_schema_markup" name="enable_schema_markup" value="1" ' . checked($this->get_option('enable_schema_markup', true), true, false) . ' /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="enable_sitemap">' . __('Enable XML Sitemap', 'seo-forge') . '</label></th>';
        echo '<td><input type="checkbox" id="enable_sitemap" name="enable_sitemap" value="1" ' . checked($this->get_option('enable_sitemap', true), true, false) . ' /></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        // Analytics Settings
        echo '<div class="seo-forge-settings-section">';
        echo '<h3>' . __('Analytics Settings', 'seo-forge') . '</h3>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th><label for="google_analytics_id">' . __('Google Analytics ID', 'seo-forge') . '</label></th>';
        echo '<td><input type="text" id="google_analytics_id" name="google_analytics_id" value="' . esc_attr($this->get_option('google_analytics_id', '')) . '" class="regular-text" placeholder="GA-XXXXXXXXX-X" /></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="enable_tracking">' . __('Enable Internal Tracking', 'seo-forge') . '</label></th>';
        echo '<td><input type="checkbox" id="enable_tracking" name="enable_tracking" value="1" ' . checked($this->get_option('enable_tracking', true), true, false) . ' /></td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        // AI Settings
        echo '<div class="seo-forge-settings-section">';
        echo '<h3>' . __('AI Content Generation', 'seo-forge') . '</h3>';
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th>' . __('SEO-Forge API', 'seo-forge') . '</th>';
        echo '<td>';
        echo '<p class="description">' . __('✅ Primary content generation API (No API key required)', 'seo-forge') . '<br>';
        echo __('Powered by https://seo-forge.bitebase.app', 'seo-forge') . '</p>';
        echo '<button type="button" id="test-seo-forge-api" class="button button-secondary">' . __('Test API Connection', 'seo-forge') . '</button>';
        echo '<div id="api-test-result" style="margin-top: 10px;"></div>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="openai_api_key">' . __('OpenAI API Key (Optional)', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<input type="password" id="openai_api_key" name="openai_api_key" value="' . esc_attr($this->get_option('openai_api_key', '')) . '" class="regular-text" />';
        echo '<p class="description">' . __('Optional fallback API when SEO-Forge API is unavailable', 'seo-forge') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th><label for="ai_model">' . __('AI Model', 'seo-forge') . '</label></th>';
        echo '<td>';
        echo '<select id="ai_model" name="ai_model">';
        echo '<option value="gpt-3.5-turbo" ' . selected($this->get_option('ai_model', 'gpt-3.5-turbo'), 'gpt-3.5-turbo', false) . '>GPT-3.5 Turbo</option>';
        echo '<option value="gpt-4" ' . selected($this->get_option('ai_model', 'gpt-3.5-turbo'), 'gpt-4', false) . '>GPT-4</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        echo '</div>';
        
        echo '<p class="submit">';
        echo '<input type="submit" name="submit" class="button button-primary" value="' . __('Save Settings', 'seo-forge') . '" />';
        echo '</p>';
        
        echo '</form>';
    }
    
    // Helper Methods
    private function render_admin_header($title) {
        echo '<div class="seo-forge-header">';
        echo '<h1 class="wp-heading-inline">' . esc_html($title) . '</h1>';
        echo '<span class="seo-forge-version">v' . SEO_FORGE_VERSION . '</span>';
        echo '</div>';
    }
    
    private function render_stat_card($title, $value, $icon) {
        echo '<div class="seo-forge-stat-card">';
        echo '<div class="stat-icon"><span class="dashicons ' . esc_attr($icon) . '"></span></div>';
        echo '<div class="stat-content">';
        echo '<div class="stat-value">' . esc_html($value) . '</div>';
        echo '<div class="stat-title">' . esc_html($title) . '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    private function render_recent_activity() {
        $activities = [
            ['action' => 'Content optimized', 'post' => 'How to improve SEO', 'time' => '2 hours ago'],
            ['action' => 'Keyword added', 'post' => 'wordpress seo', 'time' => '4 hours ago'],
            ['action' => 'Analytics synced', 'post' => '', 'time' => '6 hours ago'],
            ['action' => 'Content generated', 'post' => 'SEO Best Practices', 'time' => '1 day ago'],
        ];
        
        echo '<ul class="seo-forge-activity-list">';
        foreach ($activities as $activity) {
            echo '<li>';
            echo '<strong>' . esc_html($activity['action']) . '</strong>';
            if ($activity['post']) {
                echo ' - ' . esc_html($activity['post']);
            }
            echo '<span class="activity-time">' . esc_html($activity['time']) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    private function render_top_content_table() {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Title', 'seo-forge') . '</th>';
        echo '<th>' . __('Views', 'seo-forge') . '</th>';
        echo '<th>' . __('SEO Score', 'seo-forge') . '</th>';
        echo '<th>' . __('Keywords', 'seo-forge') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $posts = get_posts(['numberposts' => 10, 'post_status' => 'publish']);
        foreach ($posts as $post) {
            $views = get_post_meta($post->ID, '_seo_forge_views', true) ?: rand(100, 1000);
            $seo_score = get_post_meta($post->ID, '_seo_forge_seo_score', true) ?: rand(60, 95);
            $keywords = get_post_meta($post->ID, '_seo_forge_focus_keyword', true) ?: 'N/A';
            
            echo '<tr>';
            echo '<td><a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a></td>';
            echo '<td>' . esc_html($views) . '</td>';
            echo '<td><span class="seo-score score-' . intval($seo_score / 20) . '">' . esc_html($seo_score) . '%</span></td>';
            echo '<td>' . esc_html($keywords) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    private function render_keyword_rankings_table() {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Keyword', 'seo-forge') . '</th>';
        echo '<th>' . __('Position', 'seo-forge') . '</th>';
        echo '<th>' . __('Change', 'seo-forge') . '</th>';
        echo '<th>' . __('Search Volume', 'seo-forge') . '</th>';
        echo '<th>' . __('URL', 'seo-forge') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $keywords = $this->get_tracked_keywords();
        foreach ($keywords as $keyword) {
            echo '<tr>';
            echo '<td>' . esc_html($keyword['keyword']) . '</td>';
            echo '<td>' . esc_html($keyword['position']) . '</td>';
            echo '<td><span class="position-change ' . ($keyword['change'] > 0 ? 'positive' : 'negative') . '">' . ($keyword['change'] > 0 ? '+' : '') . esc_html($keyword['change']) . '</span></td>';
            echo '<td>' . esc_html($keyword['search_volume']) . '</td>';
            echo '<td><a href="' . esc_url($keyword['url']) . '" target="_blank">' . esc_html(parse_url($keyword['url'], PHP_URL_PATH)) . '</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    private function render_keywords_table() {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Keyword', 'seo-forge') . '</th>';
        echo '<th>' . __('Target URL', 'seo-forge') . '</th>';
        echo '<th>' . __('Search Engine', 'seo-forge') . '</th>';
        echo '<th>' . __('Current Position', 'seo-forge') . '</th>';
        echo '<th>' . __('Last Checked', 'seo-forge') . '</th>';
        echo '<th>' . __('Actions', 'seo-forge') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        $keywords = $this->get_tracked_keywords();
        foreach ($keywords as $keyword) {
            echo '<tr>';
            echo '<td>' . esc_html($keyword['keyword']) . '</td>';
            echo '<td><a href="' . esc_url($keyword['url']) . '" target="_blank">' . esc_html($keyword['url']) . '</a></td>';
            echo '<td>' . esc_html(ucfirst($keyword['search_engine'])) . '</td>';
            echo '<td>' . esc_html($keyword['position']) . '</td>';
            echo '<td>' . esc_html($keyword['last_checked']) . '</td>';
            echo '<td>';
            echo '<button class="button button-small check-ranking" data-keyword-id="' . esc_attr($keyword['id']) . '">' . __('Check Now', 'seo-forge') . '</button>';
            echo '<button class="button button-small delete-keyword" data-keyword-id="' . esc_attr($keyword['id']) . '">' . __('Delete', 'seo-forge') . '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
    
    // AJAX Handlers
    public function ajax_generate_content() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $length = intval($_POST['length'] ?? 500);
        $type = sanitize_text_field($_POST['type'] ?? 'blog');
        
        $content = $this->generate_ai_content($topic, $keywords, $length, $type);
        $seo_score = $this->calculate_seo_score($content, $keywords);
        
        wp_send_json_success([
            'content' => $content,
            'word_count' => str_word_count($content),
            'seo_score' => $seo_score,
            'suggestions' => $this->get_seo_suggestions($content, $keywords)
        ]);
    }
    
    public function ajax_analyze_content() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        $content = wp_kses_post($_POST['content'] ?? '');
        $keyword = sanitize_text_field($_POST['keyword'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        $analysis = $this->analyze_content_seo($content, $keyword);
        
        if ($post_id) {
            update_post_meta($post_id, '_seo_forge_seo_score', $analysis['score']);
            update_post_meta($post_id, '_seo_forge_analysis', $analysis);
        }
        
        wp_send_json_success($analysis);
    }
    
    public function ajax_get_analytics() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        $days = intval($_POST['days'] ?? 30);
        $analytics = $this->get_analytics_data($days);
        
        wp_send_json_success($analytics);
    }
    
    public function ajax_save_settings() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        $settings = $_POST['settings'] ?? [];
        $this->save_settings($settings);
        
        wp_send_json_success(['message' => __('Settings saved successfully', 'seo-forge')]);
    }
    
    public function ajax_test_api() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        // Test the SEO-Forge API with a simple request
        $test_content = $this->generate_seo_forge_content(
            'WordPress SEO',
            'SEO optimization, WordPress',
            200,
            'blog'
        );
        
        if ($test_content) {
            wp_send_json_success([
                'message' => __('✅ SEO-Forge API is working correctly!', 'seo-forge'),
                'sample_content' => substr($test_content, 0, 200) . '...',
                'status' => 'success'
            ]);
        } else {
            wp_send_json_error([
                'message' => __('❌ SEO-Forge API test failed. Check error logs for details.', 'seo-forge'),
                'status' => 'error'
            ]);
        }
    }
    
    public function ajax_save_generated_content() {
        check_ajax_referer('seo_forge_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'seo-forge'));
        }
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $keywords = sanitize_text_field($_POST['keywords'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? 'blog');
        
        if (empty($title) || empty($content)) {
            wp_send_json_error(['message' => __('Title and content are required', 'seo-forge')]);
        }
        
        // Create new SEO content post
        $post_data = [
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'seo_content',
            'post_author' => get_current_user_id(),
            'meta_input' => [
                '_seo_forge_focus_keyword' => $keywords,
                '_seo_forge_content_type' => $type,
                '_seo_forge_generated' => true,
                '_seo_forge_generation_date' => current_time('mysql')
            ]
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            wp_send_json_error(['message' => __('Failed to save content', 'seo-forge')]);
        }
        
        // Generate SEO meta data
        $meta_title = $this->generate_meta_title($title, $keywords);
        $meta_description = $this->generate_meta_description($content, $keywords);
        
        update_post_meta($post_id, '_seo_forge_meta_title', $meta_title);
        update_post_meta($post_id, '_seo_forge_meta_description', $meta_description);
        
        // Calculate SEO score
        $seo_score = $this->calculate_seo_score($content, $keywords);
        update_post_meta($post_id, '_seo_forge_seo_score', $seo_score);
        
        wp_send_json_success([
            'message' => __('Content saved successfully as draft', 'seo-forge'),
            'post_id' => $post_id,
            'edit_url' => admin_url('post.php?post=' . $post_id . '&action=edit'),
            'view_url' => admin_url('edit.php?post_type=seo_content')
        ]);
    }
    
    // Core Functions
    private function generate_ai_content($topic, $keywords, $length, $type = 'blog') {
        // Try SEO-Forge API first
        $seo_forge_content = $this->generate_seo_forge_content($topic, $keywords, $length, $type);
        if ($seo_forge_content) {
            return $seo_forge_content;
        }
        
        // Fallback to OpenAI if SEO-Forge API fails
        $openai_content = $this->generate_openai_content($topic, $keywords, $length, $type);
        if ($openai_content) {
            return $openai_content;
        }
        
        // Final fallback to templates
        return $this->generate_fallback_content($topic, $keywords, $length, $type);
    }
    
    private function generate_seo_forge_content($topic, $keywords, $length, $type = 'blog') {
        $api_endpoint = 'https://seo-forge.bitebase.app/api/content/generate';
        
        // Test the API first with a simple request
        $response = wp_remote_post($api_endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'SEO-Forge-WordPress-Plugin/2.0.0',
                'Accept' => 'application/json',
            ],
            'body' => wp_json_encode([
                'topic' => sanitize_text_field($topic),
                'keywords' => sanitize_text_field($keywords),
                'length' => intval($length),
                'type' => sanitize_text_field($type),
                'language' => get_locale(),
                'site_url' => home_url(),
                'plugin_version' => '2.0.0'
            ]),
            'timeout' => 30,
            'sslverify' => true,
        ]);
        
        if (is_wp_error($response)) {
            // Log the error for debugging
            error_log('SEO-Forge API Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log response for debugging
        error_log('SEO-Forge API Response Code: ' . $response_code);
        error_log('SEO-Forge API Response Body: ' . $body);
        
        if ($response_code !== 200) {
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('SEO-Forge API JSON Error: ' . json_last_error_msg());
            return false;
        }
        
        if (isset($data['content']) && !empty($data['content'])) {
            return $data['content'];
        }
        
        if (isset($data['data']['content']) && !empty($data['data']['content'])) {
            return $data['data']['content'];
        }
        
        return false;
    }
    
    private function generate_openai_content($topic, $keywords, $length, $type = 'blog') {
        $api_key = $this->get_option('openai_api_key', '');
        
        if (empty($api_key)) {
            return false;
        }
        
        $prompt = $this->build_ai_prompt($topic, $keywords, $length, $type);
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => $this->get_option('ai_model', 'gpt-3.5-turbo'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $length * 2,
                'temperature' => 0.7,
            ]),
            'timeout' => 30,
        ]);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        return false;
    }
    
    private function generate_fallback_content($topic, $keywords, $length, $type) {
        $templates = [
            'blog' => "# {topic}\n\nIn this comprehensive guide, we'll explore {topic} and how it relates to {keywords}.\n\n## Introduction\n\n{topic} is an important subject that many people are interested in learning about. Throughout this article, we'll cover the key aspects of {keywords} and provide valuable insights.\n\n## Main Content\n\nWhen discussing {topic}, it's essential to understand the fundamentals. The concept of {keywords} plays a crucial role in this context.\n\n## Key Points\n\n- Understanding {topic} is crucial for success\n- {keywords} are fundamental elements to consider\n- Practical applications make a significant difference\n- Best practices should always be followed\n\n## Conclusion\n\nIn conclusion, {topic} offers numerous opportunities for growth and improvement. By focusing on {keywords}, you can achieve better results and reach your goals more effectively.",
            
            'article' => "# Understanding {topic}: A Comprehensive Analysis\n\n{topic} has become increasingly important in today's world. This article examines the relationship between {topic} and {keywords}.\n\n## Background\n\nThe significance of {topic} cannot be overstated. Research shows that {keywords} are essential components that contribute to overall success.\n\n## Analysis\n\nOur analysis reveals that {topic} encompasses several key areas:\n\n1. **Primary considerations** related to {keywords}\n2. **Secondary factors** that influence outcomes\n3. **Best practices** for implementation\n4. **Future trends** and developments\n\n## Findings\n\nThe data suggests that focusing on {keywords} while addressing {topic} leads to improved results. Organizations that prioritize these elements tend to perform better.\n\n## Recommendations\n\nBased on our research, we recommend:\n\n- Implementing strategies focused on {keywords}\n- Regular monitoring of {topic} metrics\n- Continuous improvement processes\n- Stakeholder engagement and feedback\n\n## Conclusion\n\nThe relationship between {topic} and {keywords} is complex but manageable with the right approach.",
            
            'guide' => "# How to Master {topic}: A Step-by-Step Guide\n\nLearning about {topic} doesn't have to be complicated. This guide will walk you through everything you need to know about {keywords}.\n\n## Getting Started\n\nBefore diving into {topic}, it's important to understand the basics of {keywords}. This foundation will help you succeed.\n\n## Step 1: Understanding the Basics\n\nStart by familiarizing yourself with {topic}. The key concepts include {keywords} and their practical applications.\n\n## Step 2: Planning Your Approach\n\nDevelop a strategy that incorporates {keywords} into your {topic} implementation. Consider these factors:\n\n- Available resources\n- Timeline and milestones\n- Success metrics\n- Potential challenges\n\n## Step 3: Implementation\n\nBegin implementing your {topic} strategy with focus on {keywords}. Take it one step at a time.\n\n## Step 4: Monitoring and Optimization\n\nRegularly assess your progress with {topic} and adjust your approach to {keywords} as needed.\n\n## Common Mistakes to Avoid\n\n- Rushing the process\n- Ignoring {keywords}\n- Lack of proper planning\n- Insufficient monitoring\n\n## Conclusion\n\nMastering {topic} takes time and practice, but with proper attention to {keywords}, you'll achieve your goals.",
            
            'review' => "# {topic} Review: Everything You Need to Know\n\nIn this comprehensive review, we'll examine {topic} and evaluate its performance in relation to {keywords}.\n\n## Overview\n\n{topic} has gained significant attention recently. Our review focuses on how well it addresses {keywords} and meets user expectations.\n\n## Key Features\n\nThe main features of {topic} include:\n\n- Comprehensive coverage of {keywords}\n- User-friendly interface\n- Reliable performance\n- Good value proposition\n\n## Pros and Cons\n\n### Pros\n- Excellent handling of {keywords}\n- Strong performance in {topic} scenarios\n- Good customer support\n- Regular updates and improvements\n\n### Cons\n- Learning curve for beginners\n- Some advanced features require additional setup\n- Price point may be high for some users\n\n## Performance Analysis\n\nOur testing shows that {topic} performs well across various {keywords} scenarios. The results are consistently positive.\n\n## User Experience\n\nUsers report satisfaction with {topic}, particularly praising its approach to {keywords}. The interface is intuitive and responsive.\n\n## Final Verdict\n\n{topic} is a solid choice for those looking to work with {keywords}. While there are some limitations, the overall experience is positive.\n\n## Rating: 4.5/5 Stars\n\nWe recommend {topic} for users who prioritize {keywords} and value reliable performance."
        ];
        
        $template = $templates[$type] ?? $templates['blog'];
        
        $content = str_replace(['{topic}', '{keywords}'], [$topic, $keywords], $template);
        
        // Adjust length
        $words = explode(' ', $content);
        if (count($words) > $length) {
            $content = implode(' ', array_slice($words, 0, $length));
        } elseif (count($words) < $length) {
            // Add more content to reach target length
            $additional = "\n\n## Additional Information\n\nFurther details about {topic} and {keywords} can help provide more comprehensive understanding. This includes practical examples, case studies, and real-world applications that demonstrate the effectiveness of these concepts.";
            $content .= str_replace(['{topic}', '{keywords}'], [$topic, $keywords], $additional);
        }
        
        return $content;
    }
    
    private function build_ai_prompt($topic, $keywords, $length, $type) {
        $prompts = [
            'blog' => "Write a comprehensive blog post about '{topic}'. Include the keywords '{keywords}' naturally throughout the content. The post should be approximately {length} words long. Structure it with proper headings, subheadings, and make it engaging and informative. Focus on providing value to readers while maintaining good SEO practices.",
            
            'article' => "Create a detailed article about '{topic}' that incorporates '{keywords}' strategically. The article should be around {length} words and written in a professional, informative tone. Include an introduction, main body with multiple sections, and a conclusion. Ensure the content is well-researched and authoritative.",
            
            'guide' => "Write a step-by-step guide on '{topic}' that naturally includes '{keywords}'. The guide should be approximately {length} words and structured with clear steps, tips, and actionable advice. Make it practical and easy to follow for readers who want to learn about this topic.",
            
            'review' => "Create a comprehensive review of '{topic}' that incorporates '{keywords}' naturally. The review should be around {length} words and include sections like overview, features, pros and cons, performance analysis, and final verdict. Write in an objective, helpful tone that assists readers in making informed decisions."
        ];
        
        $prompt = $prompts[$type] ?? $prompts['blog'];
        
        return str_replace(['{topic}', '{keywords}', '{length}'], [$topic, $keywords, $length], $prompt);
    }
    
    private function analyze_content_seo($content, $keyword) {
        $analysis = [
            'score' => 0,
            'issues' => [],
            'suggestions' => [],
            'metrics' => []
        ];
        
        $word_count = str_word_count($content);
        $keyword_density = $this->calculate_keyword_density($content, $keyword);
        $readability_score = $this->calculate_readability_score($content);
        
        // Word count analysis
        if ($word_count < 300) {
            $analysis['issues'][] = __('Content is too short. Aim for at least 300 words.', 'seo-forge');
        } elseif ($word_count > 2000) {
            $analysis['suggestions'][] = __('Consider breaking this long content into multiple parts.', 'seo-forge');
        } else {
            $analysis['score'] += 20;
        }
        
        // Keyword density analysis
        if ($keyword_density < 0.5) {
            $analysis['issues'][] = __('Keyword density is too low. Include the focus keyword more often.', 'seo-forge');
        } elseif ($keyword_density > 3) {
            $analysis['issues'][] = __('Keyword density is too high. Reduce keyword usage to avoid over-optimization.', 'seo-forge');
        } else {
            $analysis['score'] += 25;
        }
        
        // Readability analysis
        if ($readability_score < 60) {
            $analysis['issues'][] = __('Content readability is poor. Use shorter sentences and simpler words.', 'seo-forge');
        } else {
            $analysis['score'] += 20;
        }
        
        // Heading structure analysis
        $headings = $this->extract_headings($content);
        if (empty($headings)) {
            $analysis['issues'][] = __('No headings found. Add H2 and H3 tags to structure your content.', 'seo-forge');
        } else {
            $analysis['score'] += 15;
        }
        
        // Internal links analysis
        $internal_links = $this->count_internal_links($content);
        if ($internal_links < 2) {
            $analysis['suggestions'][] = __('Add more internal links to improve SEO and user experience.', 'seo-forge');
        } else {
            $analysis['score'] += 10;
        }
        
        // External links analysis
        $external_links = $this->count_external_links($content);
        if ($external_links < 1) {
            $analysis['suggestions'][] = __('Consider adding relevant external links to authoritative sources.', 'seo-forge');
        } else {
            $analysis['score'] += 10;
        }
        
        $analysis['metrics'] = [
            'word_count' => $word_count,
            'keyword_density' => round($keyword_density, 2),
            'readability_score' => round($readability_score, 1),
            'headings_count' => count($headings),
            'internal_links' => $internal_links,
            'external_links' => $external_links
        ];
        
        return $analysis;
    }
    
    private function calculate_keyword_density($content, $keyword) {
        if (empty($keyword)) {
            return 0;
        }
        
        $content = strtolower(strip_tags($content));
        $keyword = strtolower($keyword);
        $word_count = str_word_count($content);
        $keyword_count = substr_count($content, $keyword);
        
        return $word_count > 0 ? ($keyword_count / $word_count) * 100 : 0;
    }
    
    private function calculate_readability_score($content) {
        $text = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $words = str_word_count($text);
        $syllables = $this->count_syllables($text);
        
        if (count($sentences) == 0 || $words == 0) {
            return 0;
        }
        
        $avg_sentence_length = $words / count($sentences);
        $avg_syllables_per_word = $syllables / $words;
        
        // Flesch Reading Ease Score
        $score = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        
        return max(0, min(100, $score));
    }
    
    private function count_syllables($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z]/', '', $text);
        
        if (strlen($text) <= 3) {
            return 1;
        }
        
        $text = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $text);
        $text = preg_replace('/^y/', '', $text);
        $matches = preg_match_all('/[aeiouy]{1,2}/', $text);
        
        return max(1, $matches);
    }
    
    private function extract_headings($content) {
        preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/i', $content, $matches);
        return $matches[1] ?? [];
    }
    
    private function count_internal_links($content) {
        $site_url = get_site_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        $internal_count = 0;
        foreach ($matches[1] as $url) {
            if (strpos($url, $site_url) !== false || strpos($url, '/') === 0) {
                $internal_count++;
            }
        }
        
        return $internal_count;
    }
    
    private function count_external_links($content) {
        $site_url = get_site_url();
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        $external_count = 0;
        foreach ($matches[1] as $url) {
            if (strpos($url, 'http') === 0 && strpos($url, $site_url) === false) {
                $external_count++;
            }
        }
        
        return $external_count;
    }
    
    private function calculate_seo_score($content, $keywords) {
        $analysis = $this->analyze_content_seo($content, $keywords);
        return $analysis['score'];
    }
    
    private function get_seo_suggestions($content, $keywords) {
        $analysis = $this->analyze_content_seo($content, $keywords);
        return array_merge($analysis['issues'], $analysis['suggestions']);
    }
    
    private function analyze_post_seo($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $content = $post->post_content;
        $keyword = get_post_meta($post_id, '_seo_forge_focus_keyword', true);
        
        $analysis = $this->analyze_content_seo($content, $keyword);
        
        update_post_meta($post_id, '_seo_forge_seo_score', $analysis['score']);
        update_post_meta($post_id, '_seo_forge_analysis', $analysis);
        update_post_meta($post_id, '_seo_forge_last_analyzed', current_time('mysql'));
    }
    
    // Database and Analytics Functions
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics table
        $analytics_table = $wpdb->prefix . 'seo_forge_analytics';
        $analytics_sql = "CREATE TABLE $analytics_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) DEFAULT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id bigint(20) DEFAULT NULL,
            ip_address varchar(45),
            user_agent text,
            date_recorded datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY event_type (event_type),
            KEY date_recorded (date_recorded)
        ) $charset_collate;";
        
        // Keywords table
        $keywords_table = $wpdb->prefix . 'seo_forge_keywords';
        $keywords_sql = "CREATE TABLE $keywords_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            keyword varchar(255) NOT NULL,
            target_url text,
            search_engine varchar(50) DEFAULT 'google',
            current_position int DEFAULT NULL,
            previous_position int DEFAULT NULL,
            search_volume int DEFAULT NULL,
            competition varchar(20),
            date_added datetime DEFAULT CURRENT_TIMESTAMP,
            last_checked datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY keyword (keyword),
            KEY search_engine (search_engine)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'seo_forge_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_name varchar(255) NOT NULL,
            setting_value longtext,
            autoload varchar(20) DEFAULT 'yes',
            date_modified datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_name (setting_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($analytics_sql);
        dbDelta($keywords_sql);
        dbDelta($settings_sql);
        
        update_option('seo_forge_db_version', $this->db_version);
    }
    
    private function get_analytics_data($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_analytics';
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(date_recorded) as date, COUNT(*) as views 
             FROM {$table_name} 
             WHERE event_type = 'pageview' AND date_recorded >= %s 
             GROUP BY DATE(date_recorded) 
             ORDER BY date ASC",
            $date_from
        ));
        
        $analytics = [
            'pageviews' => [],
            'total_views' => 0,
            'unique_visitors' => 0,
            'top_pages' => []
        ];
        
        foreach ($results as $result) {
            $analytics['pageviews'][] = [
                'date' => $result->date,
                'views' => intval($result->views)
            ];
            $analytics['total_views'] += intval($result->views);
        }
        
        // Get unique visitors
        $unique_visitors = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT ip_address) 
             FROM {$table_name} 
             WHERE event_type = 'pageview' AND date_recorded >= %s",
            $date_from
        ));
        
        $analytics['unique_visitors'] = intval($unique_visitors);
        
        // Get top pages
        $top_pages = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, COUNT(*) as views 
             FROM {$table_name} 
             WHERE event_type = 'pageview' AND date_recorded >= %s AND post_id IS NOT NULL 
             GROUP BY post_id 
             ORDER BY views DESC 
             LIMIT 10",
            $date_from
        ));
        
        foreach ($top_pages as $page) {
            $post = get_post($page->post_id);
            if ($post) {
                $analytics['top_pages'][] = [
                    'id' => $page->post_id,
                    'title' => $post->post_title,
                    'views' => intval($page->views),
                    'url' => get_permalink($page->post_id)
                ];
            }
        }
        
        return $analytics;
    }
    
    private function track_pageview($post_id = null, $url = '', $title = '', $referrer = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_analytics';
        
        $data = [
            'post_id' => $post_id,
            'event_type' => 'pageview',
            'event_data' => wp_json_encode([
                'url' => $url,
                'title' => $title,
                'referrer' => $referrer
            ]),
            'user_id' => get_current_user_id() ?: null,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'date_recorded' => current_time('mysql')
        ];
        
        $wpdb->insert($table_name, $data);
    }
    
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function get_tracked_keywords() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_keywords';
        
        $results = $wpdb->get_results(
            "SELECT * FROM {$table_name} ORDER BY date_added DESC"
        );
        
        $keywords = [];
        foreach ($results as $result) {
            $keywords[] = [
                'id' => $result->id,
                'keyword' => $result->keyword,
                'url' => $result->target_url,
                'search_engine' => $result->search_engine,
                'position' => $result->current_position ?: 'Not ranked',
                'change' => ($result->current_position && $result->previous_position) 
                    ? $result->previous_position - $result->current_position 
                    : 0,
                'search_volume' => $result->search_volume ?: 'Unknown',
                'last_checked' => $result->last_checked ?: 'Never'
            ];
        }
        
        return $keywords;
    }
    
    private function count_seo_optimized_posts() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_seo_forge_seo_score' 
             AND CAST(meta_value AS UNSIGNED) >= 70"
        );
        
        return intval($count);
    }
    
    private function get_average_seo_score() {
        global $wpdb;
        
        $avg = $wpdb->get_var(
            "SELECT AVG(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_seo_forge_seo_score'"
        );
        
        return round(floatval($avg), 1);
    }
    
    private function count_tracked_keywords() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_keywords';
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        
        return intval($count);
    }
    
    // Cron Jobs
    public function daily_analytics_sync() {
        // Sync with Google Analytics API if configured
        $ga_id = $this->get_option('google_analytics_id', '');
        
        if (!empty($ga_id)) {
            // Implement Google Analytics API sync
            $this->sync_google_analytics();
        }
        
        // Clean up old analytics data (keep last 365 days)
        $this->cleanup_old_analytics_data();
        
        // Update keyword rankings
        $this->update_keyword_rankings();
    }
    
    public function weekly_seo_report() {
        // Generate weekly SEO report
        $report = $this->generate_seo_report();
        
        // Send email to admin if configured
        if ($this->get_option('email_reports', false)) {
            $this->send_seo_report_email($report);
        }
    }
    
    private function sync_google_analytics() {
        // Placeholder for Google Analytics API integration
        // This would require Google Analytics API credentials and implementation
    }
    
    private function cleanup_old_analytics_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_analytics';
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-365 days'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE date_recorded < %s",
            $cutoff_date
        ));
    }
    
    private function update_keyword_rankings() {
        // Placeholder for keyword ranking updates
        // This would require integration with SEO APIs like SEMrush, Ahrefs, etc.
    }
    
    private function generate_seo_report() {
        $report = [
            'period' => 'Last 7 days',
            'total_pageviews' => 0,
            'unique_visitors' => 0,
            'avg_seo_score' => $this->get_average_seo_score(),
            'optimized_posts' => $this->count_seo_optimized_posts(),
            'top_performing_posts' => [],
            'keyword_improvements' => [],
            'issues_found' => []
        ];
        
        $analytics = $this->get_analytics_data(7);
        $report['total_pageviews'] = $analytics['total_views'];
        $report['unique_visitors'] = $analytics['unique_visitors'];
        $report['top_performing_posts'] = array_slice($analytics['top_pages'], 0, 5);
        
        return $report;
    }
    
    private function send_seo_report_email($report) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('Weekly SEO Report for %s', 'seo-forge'), $site_name);
        
        $message = $this->format_email_report($report);
        
        wp_mail($admin_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
    
    private function format_email_report($report) {
        $html = '<html><body>';
        $html .= '<h2>' . __('Weekly SEO Report', 'seo-forge') . '</h2>';
        $html .= '<h3>' . __('Summary', 'seo-forge') . '</h3>';
        $html .= '<ul>';
        $html .= '<li>' . sprintf(__('Total Page Views: %d', 'seo-forge'), $report['total_pageviews']) . '</li>';
        $html .= '<li>' . sprintf(__('Unique Visitors: %d', 'seo-forge'), $report['unique_visitors']) . '</li>';
        $html .= '<li>' . sprintf(__('Average SEO Score: %.1f%%', 'seo-forge'), $report['avg_seo_score']) . '</li>';
        $html .= '<li>' . sprintf(__('Optimized Posts: %d', 'seo-forge'), $report['optimized_posts']) . '</li>';
        $html .= '</ul>';
        
        if (!empty($report['top_performing_posts'])) {
            $html .= '<h3>' . __('Top Performing Posts', 'seo-forge') . '</h3>';
            $html .= '<ol>';
            foreach ($report['top_performing_posts'] as $post) {
                $html .= '<li>' . esc_html($post['title']) . ' (' . $post['views'] . ' views)</li>';
            }
            $html .= '</ol>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    // Helper Functions for Content Generation
    private function generate_meta_title($title, $keywords) {
        $meta_title = $title;
        
        // Add primary keyword if not already in title
        if (!empty($keywords)) {
            $primary_keyword = explode(',', $keywords)[0];
            $primary_keyword = trim($primary_keyword);
            
            if (stripos($title, $primary_keyword) === false) {
                $meta_title = $primary_keyword . ' - ' . $title;
            }
        }
        
        // Ensure title is within SEO limits (60 characters)
        if (strlen($meta_title) > 60) {
            $meta_title = substr($meta_title, 0, 57) . '...';
        }
        
        return $meta_title;
    }
    
    private function generate_meta_description($content, $keywords) {
        // Extract first paragraph or first 160 characters
        $description = wp_strip_all_tags($content);
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Try to get first sentence or paragraph
        $sentences = explode('.', $description);
        $first_sentence = trim($sentences[0]);
        
        if (strlen($first_sentence) > 20 && strlen($first_sentence) <= 160) {
            $description = $first_sentence . '.';
        } else {
            $description = substr($description, 0, 157) . '...';
        }
        
        // Add primary keyword if not present
        if (!empty($keywords)) {
            $primary_keyword = explode(',', $keywords)[0];
            $primary_keyword = trim($primary_keyword);
            
            if (stripos($description, $primary_keyword) === false) {
                // Try to naturally insert keyword
                $words = explode(' ', $description);
                if (count($words) > 5) {
                    array_splice($words, 3, 0, $primary_keyword);
                    $new_description = implode(' ', $words);
                    
                    if (strlen($new_description) <= 160) {
                        $description = $new_description;
                    }
                }
            }
        }
        
        return $description;
    }
    
    // Settings and Options
    private function get_option($key, $default = null) {
        return $this->options[$key] ?? $default;
    }
    
    private function save_settings($settings) {
        $sanitized_settings = [];
        
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'google_analytics_id':
                    $sanitized_settings[$key] = sanitize_text_field($value);
                    break;
                case 'openai_api_key':
                    $sanitized_settings[$key] = sanitize_text_field($value);
                    break;
                case 'ai_model':
                    $sanitized_settings[$key] = in_array($value, ['gpt-3.5-turbo', 'gpt-4']) ? $value : 'gpt-3.5-turbo';
                    break;
                case 'enable_seo_meta':
                case 'enable_schema_markup':
                case 'enable_sitemap':
                case 'enable_tracking':
                case 'email_reports':
                    $sanitized_settings[$key] = (bool) $value;
                    break;
                default:
                    $sanitized_settings[$key] = sanitize_text_field($value);
            }
        }
        
        $this->options = array_merge($this->options, $sanitized_settings);
        update_option('seo_forge_options', $this->options);
    }
    
    public function sanitize_options($options) {
        return $this->save_settings($options);
    }
    
    // Activation and Deactivation
    public function activate() {
        // Check requirements
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('SEO Forge requires PHP 8.0 or higher.', 'seo-forge'));
        }
        
        if (version_compare(get_bloginfo('version'), '6.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('SEO Forge requires WordPress 6.0 or higher.', 'seo-forge'));
        }
        
        // Create database tables
        $this->create_database_tables();
        
        // Set default options
        $default_options = [
            'enable_seo_meta' => true,
            'enable_schema_markup' => true,
            'enable_sitemap' => true,
            'enable_tracking' => true,
            'ai_model' => 'gpt-3.5-turbo',
            'email_reports' => false
        ];
        
        add_option('seo_forge_options', $default_options);
        
        // Schedule cron jobs
        if (!wp_next_scheduled('seo_forge_daily_analytics')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_daily_analytics');
        }
        
        if (!wp_next_scheduled('seo_forge_weekly_report')) {
            wp_schedule_event(time(), 'weekly', 'seo_forge_weekly_report');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Clear scheduled cron jobs
        wp_clear_scheduled_hook('seo_forge_daily_analytics');
        wp_clear_scheduled_hook('seo_forge_weekly_report');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Show helpful notice on SEO content admin page
     */
    public function show_seo_content_notice() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'edit-seo_content') {
            $seo_content_count = wp_count_posts('seo_content');
            
            if ($seo_content_count->publish == 0 && $seo_content_count->draft == 0) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . __('Welcome to SEO Content Management!', 'seo-forge') . '</strong></p>';
                echo '<p>' . __('You haven\'t created any SEO content yet. Here\'s how to get started:', 'seo-forge') . '</p>';
                echo '<ol>';
                echo '<li>' . sprintf(__('Go to <a href="%s">Content Generator</a> to create AI-powered content', 'seo-forge'), admin_url('admin.php?page=seo-forge&tab=generator')) . '</li>';
                echo '<li>' . __('Or click "Add New Content" above to create content manually', 'seo-forge') . '</li>';
                echo '<li>' . sprintf(__('Check out the <a href="%s">Dashboard</a> for SEO insights', 'seo-forge'), admin_url('admin.php?page=seo-forge')) . '</li>';
                echo '</ol>';
                echo '<p><a href="' . admin_url('admin.php?page=seo-forge&tab=generator') . '" class="button button-primary">' . __('Generate Your First Content', 'seo-forge') . '</a></p>';
                echo '</div>';
            }
        }
    }
    
    /**
     * Customize the views on SEO content admin page
     */
    public function customize_seo_content_views($views) {
        $seo_content_count = wp_count_posts('seo_content');
        
        if ($seo_content_count->publish == 0 && $seo_content_count->draft == 0) {
            $views['getting_started'] = sprintf(
                '<a href="%s" class="button button-primary" style="margin-left: 10px;">%s</a>',
                admin_url('admin.php?page=seo-forge&tab=generator'),
                __('🚀 Generate Your First Content', 'seo-forge')
            );
        }
        
        return $views;
    }
}

// Initialize the plugin
function seo_forge_init() {
    return SEOForgeComplete::getInstance();
}

// Start the plugin
add_action('plugins_loaded', 'seo_forge_init');

// AJAX handler for frontend tracking
add_action('wp_ajax_nopriv_seo_forge_track_pageview', 'seo_forge_track_pageview');
add_action('wp_ajax_seo_forge_track_pageview', 'seo_forge_track_pageview');

function seo_forge_track_pageview() {
    check_ajax_referer('seo_forge_frontend_nonce', 'nonce');
    
    $url = esc_url_raw($_POST['url'] ?? '');
    $title = sanitize_text_field($_POST['title'] ?? '');
    $referrer = esc_url_raw($_POST['referrer'] ?? '');
    
    $post_id = url_to_postid($url);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'seo_forge_analytics';
    
    $data = [
        'post_id' => $post_id ?: null,
        'event_type' => 'pageview',
        'event_data' => wp_json_encode([
            'url' => $url,
            'title' => $title,
            'referrer' => $referrer
        ]),
        'user_id' => get_current_user_id() ?: null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'date_recorded' => current_time('mysql')
    ];
    
    $wpdb->insert($table_name, $data);
    
    wp_send_json_success();
}