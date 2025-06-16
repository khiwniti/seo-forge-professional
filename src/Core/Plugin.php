<?php
declare(strict_types=1);

namespace SEOForge\Core;

use SEOForge\Services\Container;
use SEOForge\Services\Logger;
use SEOForge\Admin\AdminController;
use SEOForge\Frontend\FrontendController;
use SEOForge\API\RestController;
use SEOForge\Security\SecurityManager;
use SEOForge\Utils\I18n;

/**
 * Main Plugin Class
 * 
 * Implements Singleton pattern and serves as the main entry point for the plugin.
 * Manages plugin lifecycle, dependency injection, and component initialization.
 * 
 * @package SEOForge\Core
 * @since 2.0.0
 */
final class Plugin {
    
    /**
     * Plugin instance
     */
    private static ?self $instance = null;
    
    /**
     * Dependency injection container
     */
    private Container $container;
    
    /**
     * Plugin initialization status
     */
    private bool $initialized = false;
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->container = new Container();
    }
    
    /**
     * Get plugin instance (Singleton pattern)
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup(): void {
        throw new \Exception('Cannot unserialize singleton');
    }
    
    /**
     * Initialize the plugin
     * 
     * @throws \Exception If plugin is already initialized
     */
    public function init(): void {
        if ($this->initialized) {
            throw new \Exception('Plugin is already initialized');
        }
        
        try {
            $this->registerServices();
            $this->initializeComponents();
            $this->setupHooks();
            
            $this->initialized = true;
            
            // Log successful initialization
            $this->container->get(Logger::class)->info('SEO-Forge plugin initialized successfully');
            
        } catch (\Throwable $e) {
            $this->handleInitializationError($e);
            throw $e;
        }
    }
    
    /**
     * Register services in the dependency injection container
     */
    private function registerServices(): void {
        // Core services
        $this->container->singleton(Logger::class, function() {
            return new Logger('seo-forge');
        });
        
        $this->container->singleton(SecurityManager::class, function($container) {
            return new SecurityManager($container->get(Logger::class));
        });
        
        $this->container->singleton(I18n::class, function() {
            return new I18n();
        });
        
        // Controllers
        $this->container->singleton(AdminController::class, function($container) {
            return new AdminController(
                $container->get(Logger::class),
                $container->get(SecurityManager::class)
            );
        });
        
        $this->container->singleton(FrontendController::class, function($container) {
            return new FrontendController(
                $container->get(Logger::class),
                $container->get(SecurityManager::class)
            );
        });
        
        $this->container->singleton(RestController::class, function($container) {
            return new RestController(
                $container->get(Logger::class),
                $container->get(SecurityManager::class)
            );
        });
    }
    
    /**
     * Initialize plugin components
     */
    private function initializeComponents(): void {
        // Initialize internationalization
        $this->container->get(I18n::class)->loadTextDomain();
        
        // Initialize security manager
        $this->container->get(SecurityManager::class)->init();
        
        // Initialize controllers based on context
        if (is_admin()) {
            $this->container->get(AdminController::class)->init();
        } else {
            $this->container->get(FrontendController::class)->init();
        }
        
        // Always initialize REST API
        $this->container->get(RestController::class)->init();
    }
    
    /**
     * Setup WordPress hooks
     */
    private function setupHooks(): void {
        // Plugin lifecycle hooks
        add_action('init', [$this, 'onInit'], 10);
        add_action('wp_loaded', [$this, 'onWpLoaded'], 10);
        
        // Admin hooks
        if (is_admin()) {
            add_action('admin_init', [$this, 'onAdminInit'], 10);
            add_action('admin_menu', [$this, 'onAdminMenu'], 10);
        }
        
        // REST API hooks
        add_action('rest_api_init', [$this, 'onRestApiInit'], 10);
        
        // Security hooks
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);
        add_action('wp_login_failed', [$this, 'onLoginFailed'], 10, 1);
        
        // Performance hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts'], 10);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts'], 10);
        
        // Custom hooks for extensibility
        do_action('seo_forge_plugin_loaded', $this);
    }
    
    /**
     * WordPress init hook callback
     */
    public function onInit(): void {
        // Register custom post types and taxonomies
        $this->registerCustomPostTypes();
        $this->registerCustomTaxonomies();
        
        // Initialize cron jobs
        $this->initializeCronJobs();
        
        do_action('seo_forge_init', $this);
    }
    
    /**
     * WordPress wp_loaded hook callback
     */
    public function onWpLoaded(): void {
        // Plugin is fully loaded
        do_action('seo_forge_loaded', $this);
    }
    
    /**
     * Admin init hook callback
     */
    public function onAdminInit(): void {
        // Admin-specific initialization
        do_action('seo_forge_admin_init', $this);
    }
    
    /**
     * Admin menu hook callback
     */
    public function onAdminMenu(): void {
        $this->container->get(AdminController::class)->registerMenus();
    }
    
    /**
     * REST API init hook callback
     */
    public function onRestApiInit(): void {
        $this->container->get(RestController::class)->registerRoutes();
    }
    
    /**
     * User login hook callback
     */
    public function onUserLogin(string $user_login, \WP_User $user): void {
        $this->container->get(SecurityManager::class)->logUserLogin($user_login, $user);
    }
    
    /**
     * Login failed hook callback
     */
    public function onLoginFailed(string $username): void {
        $this->container->get(SecurityManager::class)->logFailedLogin($username);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void {
        if (!is_admin()) {
            $this->container->get(FrontendController::class)->enqueueAssets();
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts(): void {
        $this->container->get(AdminController::class)->enqueueAssets();
    }
    
    /**
     * Register custom post types
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
        ]);
    }
    
    /**
     * Register custom taxonomies
     */
    private function registerCustomTaxonomies(): void {
        // SEO Categories taxonomy
        register_taxonomy('seo_category', ['seo_content'], [
            'labels' => [
                'name' => __('SEO Categories', 'seo-forge'),
                'singular_name' => __('SEO Category', 'seo-forge'),
            ],
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
        ]);
    }
    
    /**
     * Initialize cron jobs
     */
    private function initializeCronJobs(): void {
        // Schedule content generation job
        if (!wp_next_scheduled('seo_forge_generate_content')) {
            wp_schedule_event(time(), 'hourly', 'seo_forge_generate_content');
        }
        
        // Schedule analytics sync job
        if (!wp_next_scheduled('seo_forge_sync_analytics')) {
            wp_schedule_event(time(), 'daily', 'seo_forge_sync_analytics');
        }
        
        // Register cron job callbacks
        add_action('seo_forge_generate_content', [$this, 'runContentGeneration']);
        add_action('seo_forge_sync_analytics', [$this, 'runAnalyticsSync']);
    }
    
    /**
     * Run content generation cron job
     */
    public function runContentGeneration(): void {
        try {
            // Content generation logic will be implemented in BlogGenerator service
            do_action('seo_forge_run_content_generation');
            
            $this->container->get(Logger::class)->info('Content generation cron job completed');
        } catch (\Throwable $e) {
            $this->container->get(Logger::class)->error('Content generation cron job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Run analytics sync cron job
     */
    public function runAnalyticsSync(): void {
        try {
            // Analytics sync logic will be implemented in Analytics service
            do_action('seo_forge_run_analytics_sync');
            
            $this->container->get(Logger::class)->info('Analytics sync cron job completed');
        } catch (\Throwable $e) {
            $this->container->get(Logger::class)->error('Analytics sync cron job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get the dependency injection container
     */
    public function getContainer(): Container {
        return $this->container;
    }
    
    /**
     * Get plugin version
     */
    public function getVersion(): string {
        return \SEOForge\PLUGIN_VERSION;
    }
    
    /**
     * Check if plugin is initialized
     */
    public function isInitialized(): bool {
        return $this->initialized;
    }
    
    /**
     * Handle initialization errors
     */
    private function handleInitializationError(\Throwable $e): void {
        // Log the error if logger is available
        try {
            if ($this->container->has(Logger::class)) {
                $this->container->get(Logger::class)->critical('Plugin initialization failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } catch (\Throwable $logError) {
            // Fallback to error_log if logger fails
            error_log('SEO-Forge: Failed to log initialization error: ' . $logError->getMessage());
        }
        
        // Fallback error logging
        error_log('SEO-Forge Plugin Initialization Error: ' . $e->getMessage());
    }
}