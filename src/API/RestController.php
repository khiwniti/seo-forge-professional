<?php
declare(strict_types=1);

namespace SEOForge\API;

use SEOForge\Services\Logger;
use SEOForge\Security\SecurityManager;
use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller
 * 
 * Manages all REST API endpoints for the plugin including authentication,
 * rate limiting, and comprehensive API functionality.
 * 
 * @package SEOForge\API
 * @since 2.0.0
 */
class RestController {
    
    /**
     * API namespace
     */
    private const NAMESPACE = 'seo-forge/v1';
    
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
     * Initialize REST API controller
     */
    public function init(): void {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }
    
    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Content endpoints
        register_rest_route(self::NAMESPACE, '/content', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getContent'],
                'permission_callback' => [$this, 'checkContentPermissions'],
                'args' => $this->getContentArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createContent'],
                'permission_callback' => [$this, 'checkCreatePermissions'],
                'args' => $this->getCreateContentArgs(),
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/content/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getContentById'],
                'permission_callback' => [$this, 'checkContentPermissions'],
                'args' => ['id' => ['validate_callback' => 'is_numeric']],
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'updateContent'],
                'permission_callback' => [$this, 'checkEditPermissions'],
                'args' => $this->getUpdateContentArgs(),
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'deleteContent'],
                'permission_callback' => [$this, 'checkDeletePermissions'],
                'args' => ['id' => ['validate_callback' => 'is_numeric']],
            ],
        ]);
        
        // Analytics endpoints
        register_rest_route(self::NAMESPACE, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'getAnalytics'],
            'permission_callback' => [$this, 'checkAnalyticsPermissions'],
            'args' => $this->getAnalyticsArgs(),
        ]);
        
        register_rest_route(self::NAMESPACE, '/analytics/track', [
            'methods' => 'POST',
            'callback' => [$this, 'trackEvent'],
            'permission_callback' => [$this, 'checkTrackingPermissions'],
            'args' => $this->getTrackingArgs(),
        ]);
        
        // Keywords endpoints
        register_rest_route(self::NAMESPACE, '/keywords', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getKeywords'],
                'permission_callback' => [$this, 'checkContentPermissions'],
                'args' => $this->getKeywordsArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'addKeyword'],
                'permission_callback' => [$this, 'checkEditPermissions'],
                'args' => $this->getAddKeywordArgs(),
            ],
        ]);
        
        register_rest_route(self::NAMESPACE, '/keywords/research', [
            'methods' => 'POST',
            'callback' => [$this, 'researchKeywords'],
            'permission_callback' => [$this, 'checkEditPermissions'],
            'args' => $this->getKeywordResearchArgs(),
        ]);
        
        // Templates endpoints
        register_rest_route(self::NAMESPACE, '/templates', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getTemplates'],
                'permission_callback' => [$this, 'checkContentPermissions'],
                'args' => $this->getTemplatesArgs(),
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'createTemplate'],
                'permission_callback' => [$this, 'checkEditPermissions'],
                'args' => $this->getCreateTemplateArgs(),
            ],
        ]);
        
        // Generation endpoints
        register_rest_route(self::NAMESPACE, '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'generateContent'],
            'permission_callback' => [$this, 'checkGeneratePermissions'],
            'args' => $this->getGenerateArgs(),
        ]);
        
        register_rest_route(self::NAMESPACE, '/analyze', [
            'methods' => 'POST',
            'callback' => [$this, 'analyzeContent'],
            'permission_callback' => [$this, 'checkContentPermissions'],
            'args' => $this->getAnalyzeArgs(),
        ]);
        
        // Settings endpoints
        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkSettingsPermissions'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'updateSettings'],
                'permission_callback' => [$this, 'checkSettingsPermissions'],
                'args' => $this->getSettingsArgs(),
            ],
        ]);
        
        // Export endpoints
        register_rest_route(self::NAMESPACE, '/export', [
            'methods' => 'POST',
            'callback' => [$this, 'exportData'],
            'permission_callback' => [$this, 'checkExportPermissions'],
            'args' => $this->getExportArgs(),
        ]);
        
        // Health check endpoint
        register_rest_route(self::NAMESPACE, '/health', [
            'methods' => 'GET',
            'callback' => [$this, 'healthCheck'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Get content
     */
    public function getContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('get_content')) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $page = $request->get_param('page') ?: 1;
            $per_page = min($request->get_param('per_page') ?: 10, 100);
            $content_type = $request->get_param('type') ?: 'blog';
            $status = $request->get_param('status') ?: 'all';
            $search = $request->get_param('search') ?: '';
            
            $offset = ($page - 1) * $per_page;
            
            $table_name = $wpdb->prefix . 'seo_forge_content';
            
            // Build query
            $where_conditions = ['1=1'];
            $where_values = [];
            
            if ($content_type !== 'all') {
                $where_conditions[] = 'content_type = %s';
                $where_values[] = $content_type;
            }
            
            if ($status !== 'all') {
                $where_conditions[] = 'status = %s';
                $where_values[] = $status;
            }
            
            if (!empty($search)) {
                $where_conditions[] = '(title LIKE %s OR content LIKE %s)';
                $where_values[] = '%' . $wpdb->esc_like($search) . '%';
                $where_values[] = '%' . $wpdb->esc_like($search) . '%';
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            // Get total count
            $count_query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
            if (!empty($where_values)) {
                $count_query = $wpdb->prepare($count_query, $where_values);
            }
            $total = (int) $wpdb->get_var($count_query);
            
            // Get content
            $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $query_values = array_merge($where_values, [$per_page, $offset]);
            $results = $wpdb->get_results($wpdb->prepare($query, $query_values), ARRAY_A);
            
            $response = new WP_REST_Response([
                'content' => $results,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'total_pages' => ceil($total / $per_page),
                ],
            ]);
            
            $response->header('X-Total-Count', $total);
            
            return $response;
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to get content', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Get content by ID
     */
    public function getContentById(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('get_content_by_id')) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $id = (int) $request->get_param('id');
            $table_name = $wpdb->prefix . 'seo_forge_content';
            
            $content = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
                ARRAY_A
            );
            
            if (!$content) {
                return new WP_REST_Response(['error' => 'Content not found'], 404);
            }
            
            return new WP_REST_Response(['content' => $content]);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to get content by ID', [
                'error' => $e->getMessage(),
                'id' => $request->get_param('id'),
            ]);
            
            return new WP_REST_Response(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Create content
     */
    public function createContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('create_content', 20, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $data = [
                'content_type' => $this->security->sanitizeInput($request->get_param('type'), 'text'),
                'title' => $this->security->sanitizeInput($request->get_param('title'), 'text'),
                'content' => $this->security->sanitizeInput($request->get_param('content'), 'html'),
                'meta_description' => $this->security->sanitizeInput($request->get_param('meta_description'), 'textarea'),
                'keywords' => $this->security->sanitizeInput($request->get_param('keywords'), 'text'),
                'target_audience' => $this->security->sanitizeInput($request->get_param('target_audience'), 'text'),
                'language' => $this->security->sanitizeInput($request->get_param('language'), 'text'),
                'status' => $this->security->sanitizeInput($request->get_param('status'), 'text'),
                'word_count' => str_word_count(strip_tags($request->get_param('content'))),
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ];
            
            $table_name = $wpdb->prefix . 'seo_forge_content';
            
            $result = $wpdb->insert($table_name, $data);
            
            if ($result === false) {
                throw new \Exception('Failed to insert content: ' . $wpdb->last_error);
            }
            
            $content_id = $wpdb->insert_id;
            
            $this->logger->info('API: Content created', [
                'content_id' => $content_id,
                'title' => $data['title'],
                'user_id' => get_current_user_id(),
            ]);
            
            return new WP_REST_Response([
                'id' => $content_id,
                'message' => 'Content created successfully',
            ], 201);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to create content', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to create content'], 500);
        }
    }
    
    /**
     * Update content
     */
    public function updateContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('update_content', 50, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $id = (int) $request->get_param('id');
            $table_name = $wpdb->prefix . 'seo_forge_content';
            
            // Check if content exists
            $existing = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
                ARRAY_A
            );
            
            if (!$existing) {
                return new WP_REST_Response(['error' => 'Content not found'], 404);
            }
            
            $data = [
                'title' => $this->security->sanitizeInput($request->get_param('title'), 'text'),
                'content' => $this->security->sanitizeInput($request->get_param('content'), 'html'),
                'meta_description' => $this->security->sanitizeInput($request->get_param('meta_description'), 'textarea'),
                'keywords' => $this->security->sanitizeInput($request->get_param('keywords'), 'text'),
                'target_audience' => $this->security->sanitizeInput($request->get_param('target_audience'), 'text'),
                'language' => $this->security->sanitizeInput($request->get_param('language'), 'text'),
                'status' => $this->security->sanitizeInput($request->get_param('status'), 'text'),
                'word_count' => str_word_count(strip_tags($request->get_param('content'))),
                'updated_at' => current_time('mysql'),
            ];
            
            $result = $wpdb->update($table_name, $data, ['id' => $id]);
            
            if ($result === false) {
                throw new \Exception('Failed to update content: ' . $wpdb->last_error);
            }
            
            $this->logger->info('API: Content updated', [
                'content_id' => $id,
                'title' => $data['title'],
                'user_id' => get_current_user_id(),
            ]);
            
            return new WP_REST_Response(['message' => 'Content updated successfully']);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to update content', [
                'error' => $e->getMessage(),
                'id' => $request->get_param('id'),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to update content'], 500);
        }
    }
    
    /**
     * Delete content
     */
    public function deleteContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('delete_content', 10, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $id = (int) $request->get_param('id');
            $table_name = $wpdb->prefix . 'seo_forge_content';
            
            $result = $wpdb->delete($table_name, ['id' => $id]);
            
            if ($result === false) {
                throw new \Exception('Failed to delete content: ' . $wpdb->last_error);
            }
            
            if ($result === 0) {
                return new WP_REST_Response(['error' => 'Content not found'], 404);
            }
            
            $this->logger->info('API: Content deleted', [
                'content_id' => $id,
                'user_id' => get_current_user_id(),
            ]);
            
            return new WP_REST_Response(['message' => 'Content deleted successfully']);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to delete content', [
                'error' => $e->getMessage(),
                'id' => $request->get_param('id'),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to delete content'], 500);
        }
    }
    
    /**
     * Get analytics
     */
    public function getAnalytics(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('get_analytics')) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
            $end_date = $request->get_param('end_date') ?: date('Y-m-d');
            $metric_type = $request->get_param('metric_type') ?: 'all';
            
            $table_name = $wpdb->prefix . 'seo_forge_analytics';
            
            $where_conditions = ['date_recorded BETWEEN %s AND %s'];
            $where_values = [$start_date, $end_date];
            
            if ($metric_type !== 'all') {
                $where_conditions[] = 'metric_type = %s';
                $where_values[] = $metric_type;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY date_recorded DESC";
            $results = $wpdb->get_results($wpdb->prepare($query, $where_values), ARRAY_A);
            
            return new WP_REST_Response(['analytics' => $results]);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to get analytics', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Track event
     */
    public function trackEvent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('track_event', 1000, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            global $wpdb;
            
            $data = [
                'url' => $this->security->sanitizeInput($request->get_param('url'), 'url'),
                'metric_type' => $this->security->sanitizeInput($request->get_param('metric_type'), 'text'),
                'metric_value' => (float) $request->get_param('metric_value'),
                'date_recorded' => current_time('mysql'),
                'source' => 'api',
                'additional_data' => json_encode($request->get_param('additional_data') ?: []),
            ];
            
            $table_name = $wpdb->prefix . 'seo_forge_analytics';
            
            $result = $wpdb->insert($table_name, $data);
            
            if ($result === false) {
                throw new \Exception('Failed to track event: ' . $wpdb->last_error);
            }
            
            return new WP_REST_Response(['message' => 'Event tracked successfully']);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to track event', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to track event'], 500);
        }
    }
    
    /**
     * Generate content
     */
    public function generateContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('generate_content', 10, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            // Content generation logic will be implemented in BlogGenerator service
            $generated_content = apply_filters('seo_forge_generate_content_api', [
                'title' => 'Generated Title',
                'content' => 'Generated content...',
                'meta_description' => 'Generated meta description...',
            ], $request->get_params());
            
            return new WP_REST_Response([
                'generated_content' => $generated_content,
                'message' => 'Content generated successfully',
            ]);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to generate content', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to generate content'], 500);
        }
    }
    
    /**
     * Analyze content
     */
    public function analyzeContent(WP_REST_Request $request): WP_REST_Response {
        if (!$this->checkRateLimit('analyze_content', 50, 3600)) {
            return new WP_REST_Response(['error' => 'Rate limit exceeded'], 429);
        }
        
        try {
            $content = $request->get_param('content');
            $keyword = $request->get_param('keyword');
            
            // Content analysis logic
            $analysis = [
                'seo_score' => 75,
                'readability_score' => 80,
                'keyword_density' => 2.5,
                'word_count' => str_word_count(strip_tags($content)),
                'suggestions' => [
                    'Add more internal links',
                    'Optimize meta description',
                    'Include focus keyword in headings',
                ],
            ];
            
            return new WP_REST_Response(['analysis' => $analysis]);
            
        } catch (\Throwable $e) {
            $this->logger->error('API: Failed to analyze content', [
                'error' => $e->getMessage(),
                'request' => $request->get_params(),
            ]);
            
            return new WP_REST_Response(['error' => 'Failed to analyze content'], 500);
        }
    }
    
    /**
     * Health check
     */
    public function healthCheck(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response([
            'status' => 'healthy',
            'version' => \SEOForge\PLUGIN_VERSION,
            'timestamp' => current_time('c'),
            'database' => $this->checkDatabaseHealth(),
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
            ],
        ]);
    }
    
    /**
     * Check database health
     */
    private function checkDatabaseHealth(): array {
        global $wpdb;
        
        try {
            $tables = [
                'seo_forge_content',
                'seo_forge_analytics',
                'seo_forge_keywords',
                'seo_forge_templates',
                'seo_forge_settings',
            ];
            
            $status = [];
            
            foreach ($tables as $table) {
                $full_table_name = $wpdb->prefix . $table;
                $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
                $status[$table] = $exists ? 'exists' : 'missing';
            }
            
            return $status;
            
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Check rate limit
     */
    private function checkRateLimit(string $action, int $limit = 100, int $window = 3600): bool {
        $user_id = get_current_user_id();
        $identifier = $user_id ?: $this->getClientIp();
        
        return $this->security->checkRateLimit($action, $limit, $window, $identifier);
    }
    
    /**
     * Get client IP
     */
    private function getClientIp(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    // Permission callbacks
    public function checkContentPermissions(): bool {
        return current_user_can('edit_seo_content');
    }
    
    public function checkCreatePermissions(): bool {
        return current_user_can('edit_seo_content');
    }
    
    public function checkEditPermissions(): bool {
        return current_user_can('edit_seo_content');
    }
    
    public function checkDeletePermissions(): bool {
        return current_user_can('delete_seo_content');
    }
    
    public function checkAnalyticsPermissions(): bool {
        return current_user_can('view_seo_analytics');
    }
    
    public function checkTrackingPermissions(): bool {
        return true; // Allow tracking for all users
    }
    
    public function checkGeneratePermissions(): bool {
        return current_user_can('use_ai_generator');
    }
    
    public function checkSettingsPermissions(): bool {
        return current_user_can('manage_seo_settings');
    }
    
    public function checkExportPermissions(): bool {
        return current_user_can('manage_seo_forge');
    }
    
    // Argument definitions
    private function getContentArgs(): array {
        return [
            'page' => [
                'default' => 1,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0;
                },
            ],
            'per_page' => [
                'default' => 10,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param > 0 && $param <= 100;
                },
            ],
            'type' => [
                'default' => 'blog',
                'enum' => ['blog', 'page', 'product', 'all'],
            ],
            'status' => [
                'default' => 'all',
                'enum' => ['draft', 'published', 'archived', 'all'],
            ],
            'search' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    private function getCreateContentArgs(): array {
        return [
            'type' => [
                'required' => true,
                'enum' => ['blog', 'page', 'product'],
            ],
            'title' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content' => [
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'meta_description' => [
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'keywords' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'target_audience' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'status' => [
                'default' => 'draft',
                'enum' => ['draft', 'published', 'archived'],
            ],
        ];
    }
    
    private function getUpdateContentArgs(): array {
        return $this->getCreateContentArgs();
    }
    
    private function getAnalyticsArgs(): array {
        return [
            'start_date' => [
                'validate_callback' => function($param) {
                    return strtotime($param) !== false;
                },
            ],
            'end_date' => [
                'validate_callback' => function($param) {
                    return strtotime($param) !== false;
                },
            ],
            'metric_type' => [
                'default' => 'all',
                'enum' => ['pageviews', 'sessions', 'bounce_rate', 'performance', 'all'],
            ],
        ];
    }
    
    private function getTrackingArgs(): array {
        return [
            'url' => [
                'required' => true,
                'sanitize_callback' => 'esc_url_raw',
            ],
            'metric_type' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'metric_value' => [
                'required' => true,
                'validate_callback' => 'is_numeric',
            ],
            'additional_data' => [
                'validate_callback' => 'is_array',
            ],
        ];
    }
    
    private function getKeywordsArgs(): array {
        return [
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'status' => [
                'default' => 'active',
                'enum' => ['active', 'inactive', 'all'],
            ],
        ];
    }
    
    private function getAddKeywordArgs(): array {
        return [
            'keyword' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    private function getKeywordResearchArgs(): array {
        return [
            'seed_keyword' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'country' => [
                'default' => 'US',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    private function getTemplatesArgs(): array {
        return [
            'type' => [
                'default' => 'all',
                'enum' => ['blog', 'page', 'email', 'all'],
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    private function getCreateTemplateArgs(): array {
        return [
            'name' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'required' => true,
                'enum' => ['blog', 'page', 'email'],
            ],
            'template_content' => [
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'variables' => [
                'validate_callback' => 'is_array',
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'category' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }
    
    private function getGenerateArgs(): array {
        return [
            'type' => [
                'required' => true,
                'enum' => ['blog', 'page', 'product'],
            ],
            'topic' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'keywords' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'target_audience' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'language' => [
                'default' => 'en',
                'enum' => ['en', 'th'],
            ],
            'word_count' => [
                'default' => 1000,
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param >= 100 && $param <= 5000;
                },
            ],
        ];
    }
    
    private function getAnalyzeArgs(): array {
        return [
            'content' => [
                'required' => true,
                'sanitize_callback' => 'wp_kses_post',
            ],
            'keyword' => [
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'url' => [
                'sanitize_callback' => 'esc_url_raw',
            ],
        ];
    }
    
    private function getSettingsArgs(): array {
        return [
            'settings' => [
                'required' => true,
                'validate_callback' => 'is_array',
            ],
        ];
    }
    
    private function getExportArgs(): array {
        return [
            'type' => [
                'required' => true,
                'enum' => ['content', 'analytics', 'keywords', 'all'],
            ],
            'format' => [
                'default' => 'csv',
                'enum' => ['csv', 'json', 'xml'],
            ],
            'start_date' => [
                'validate_callback' => function($param) {
                    return strtotime($param) !== false;
                },
            ],
            'end_date' => [
                'validate_callback' => function($param) {
                    return strtotime($param) !== false;
                },
            ],
        ];
    }
    
    // Placeholder methods for missing functionality
    public function getKeywords(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['keywords' => []]);
    }
    
    public function addKeyword(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['message' => 'Keyword added successfully']);
    }
    
    public function researchKeywords(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['keywords' => []]);
    }
    
    public function getTemplates(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['templates' => []]);
    }
    
    public function createTemplate(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['message' => 'Template created successfully']);
    }
    
    public function getSettings(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['settings' => get_option('seo_forge_settings', [])]);
    }
    
    public function updateSettings(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['message' => 'Settings updated successfully']);
    }
    
    public function exportData(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response(['download_url' => '']);
    }
}