<?php
declare(strict_types=1);

namespace SEOForge\Security;

use SEOForge\Services\Logger;
use Psr\Log\LoggerInterface;

/**
 * Security Manager
 * 
 * Comprehensive security management for the plugin including input validation,
 * output escaping, CSRF protection, rate limiting, and security monitoring.
 * 
 * @package SEOForge\Security
 * @since 2.0.0
 */
class SecurityManager {
    
    /**
     * Logger instance
     */
    private LoggerInterface $logger;
    
    /**
     * Rate limiting storage
     */
    private array $rateLimits = [];
    
    /**
     * Security events
     */
    private array $securityEvents = [];
    
    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
    }
    
    /**
     * Initialize security manager
     */
    public function init(): void {
        $this->setupSecurityHooks();
        $this->initializeRateLimiting();
        $this->setupSecurityHeaders();
    }
    
    /**
     * Setup security-related WordPress hooks
     */
    private function setupSecurityHooks(): void {
        // Input sanitization hooks
        add_filter('seo_forge_sanitize_input', [$this, 'sanitizeInput'], 10, 2);
        
        // Output escaping hooks
        add_filter('seo_forge_escape_output', [$this, 'escapeOutput'], 10, 2);
        
        // CSRF protection hooks
        add_action('wp_ajax_seo_forge_action', [$this, 'verifyCsrfToken'], 1);
        add_action('wp_ajax_nopriv_seo_forge_action', [$this, 'verifyCsrfToken'], 1);
        
        // Security monitoring hooks
        add_action('wp_login_failed', [$this, 'handleFailedLogin'], 10, 1);
        add_action('wp_login', [$this, 'handleSuccessfulLogin'], 10, 2);
        
        // File upload security
        add_filter('wp_handle_upload_prefilter', [$this, 'validateFileUpload'], 10, 1);
        
        // SQL injection prevention
        add_filter('query', [$this, 'monitorDatabaseQueries'], 10, 1);
    }
    
    /**
     * Initialize rate limiting
     */
    private function initializeRateLimiting(): void {
        // Clean up old rate limit entries
        add_action('wp_scheduled_delete', [$this, 'cleanupRateLimits']);
        
        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wp_scheduled_delete')) {
            wp_schedule_event(time(), 'hourly', 'wp_scheduled_delete');
        }
    }
    
    /**
     * Setup security headers
     */
    private function setupSecurityHeaders(): void {
        if (!is_admin()) {
            add_action('send_headers', [$this, 'addSecurityHeaders']);
        }
    }
    
    /**
     * Sanitize input data
     * 
     * @param mixed $input Input data to sanitize
     * @param string $type Type of sanitization to apply
     * @return mixed Sanitized data
     */
    public function sanitizeInput($input, string $type = 'text') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'email':
                return sanitize_email($input);
                
            case 'url':
                return esc_url_raw($input);
                
            case 'int':
                return (int) $input;
                
            case 'float':
                return (float) $input;
                
            case 'bool':
                return (bool) $input;
                
            case 'html':
                return wp_kses_post($input);
                
            case 'textarea':
                return sanitize_textarea_field($input);
                
            case 'key':
                return sanitize_key($input);
                
            case 'slug':
                return sanitize_title($input);
                
            case 'filename':
                return sanitize_file_name($input);
                
            case 'text':
            default:
                return sanitize_text_field($input);
        }
    }
    
    /**
     * Escape output data
     * 
     * @param mixed $output Output data to escape
     * @param string $context Context for escaping
     * @return mixed Escaped data
     */
    public function escapeOutput($output, string $context = 'html') {
        if (is_array($output)) {
            return array_map(function($item) use ($context) {
                return $this->escapeOutput($item, $context);
            }, $output);
        }
        
        switch ($context) {
            case 'attr':
                return esc_attr($output);
                
            case 'url':
                return esc_url($output);
                
            case 'js':
                return esc_js($output);
                
            case 'textarea':
                return esc_textarea($output);
                
            case 'sql':
                global $wpdb;
                return $wpdb->prepare('%s', $output);
                
            case 'html':
            default:
                return esc_html($output);
        }
    }
    
    /**
     * Generate CSRF token
     * 
     * @param string $action Action name
     * @return string CSRF token
     */
    public function generateCsrfToken(string $action): string {
        return wp_create_nonce('seo_forge_' . $action);
    }
    
    /**
     * Verify CSRF token
     * 
     * @param string $token Token to verify
     * @param string $action Action name
     * @return bool True if valid, false otherwise
     */
    public function verifyCsrfToken(string $token = '', string $action = ''): bool {
        if (empty($token)) {
            $token = $_REQUEST['_wpnonce'] ?? '';
        }
        
        if (empty($action)) {
            $action = $_REQUEST['action'] ?? '';
        }
        
        $isValid = wp_verify_nonce($token, 'seo_forge_' . $action);
        
        if (!$isValid) {
            $this->logSecurityEvent('csrf_token_invalid', [
                'action' => $action,
                'token' => $token,
                'ip' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ]);
            
            wp_die(
                esc_html__('Security check failed. Please refresh the page and try again.', 'seo-forge'),
                esc_html__('Security Error', 'seo-forge'),
                ['response' => 403]
            );
        }
        
        return $isValid;
    }
    
    /**
     * Check rate limit for an action
     * 
     * @param string $action Action name
     * @param int $limit Maximum attempts
     * @param int $window Time window in seconds
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @return bool True if within limit, false otherwise
     */
    public function checkRateLimit(string $action, int $limit = 10, int $window = 3600, string $identifier = ''): bool {
        if (empty($identifier)) {
            $identifier = $this->getClientIp();
        }
        
        $key = $action . '_' . $identifier;
        $now = time();
        
        // Initialize if not exists
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = [];
        }
        
        // Remove old entries
        $this->rateLimits[$key] = array_filter(
            $this->rateLimits[$key],
            function($timestamp) use ($now, $window) {
                return ($now - $timestamp) < $window;
            }
        );
        
        // Check if limit exceeded
        if (count($this->rateLimits[$key]) >= $limit) {
            $this->logSecurityEvent('rate_limit_exceeded', [
                'action' => $action,
                'identifier' => $identifier,
                'limit' => $limit,
                'window' => $window,
                'attempts' => count($this->rateLimits[$key]),
            ]);
            
            return false;
        }
        
        // Add current attempt
        $this->rateLimits[$key][] = $now;
        
        return true;
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file File data
     * @return array Modified file data
     */
    public function validateFileUpload(array $file): array {
        // Check file size
        $maxSize = wp_max_upload_size();
        if ($file['size'] > $maxSize) {
            $file['error'] = sprintf(
                __('File size exceeds maximum allowed size of %s.', 'seo-forge'),
                size_format($maxSize)
            );
            return $file;
        }
        
        // Check file type
        $allowedTypes = get_allowed_mime_types();
        $fileType = wp_check_filetype($file['name'], $allowedTypes);
        
        if (!$fileType['type']) {
            $file['error'] = __('File type not allowed.', 'seo-forge');
            return $file;
        }
        
        // Check for malicious content
        if ($this->containsMaliciousContent($file['tmp_name'])) {
            $file['error'] = __('File contains potentially malicious content.', 'seo-forge');
            
            $this->logSecurityEvent('malicious_file_upload', [
                'filename' => $file['name'],
                'type' => $file['type'],
                'size' => $file['size'],
                'ip' => $this->getClientIp(),
            ]);
            
            return $file;
        }
        
        return $file;
    }
    
    /**
     * Monitor database queries for potential SQL injection
     * 
     * @param string $query SQL query
     * @return string Query (unchanged)
     */
    public function monitorDatabaseQueries(string $query): string {
        // Patterns that might indicate SQL injection attempts
        $suspiciousPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+.*set/i',
            '/exec\s*\(/i',
            '/script\s*>/i',
            '/javascript:/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $this->logSecurityEvent('suspicious_sql_query', [
                    'query' => $query,
                    'pattern' => $pattern,
                    'ip' => $this->getClientIp(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]);
                break;
            }
        }
        
        return $query;
    }
    
    /**
     * Handle failed login attempt
     * 
     * @param string $username Username that failed
     */
    public function handleFailedLogin(string $username): void {
        $ip = $this->getClientIp();
        
        $this->logSecurityEvent('login_failed', [
            'username' => $username,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
        
        // Check for brute force attempts
        if (!$this->checkRateLimit('login_attempt', 5, 900, $ip)) {
            // Block IP temporarily
            $this->blockIpTemporarily($ip, 3600); // 1 hour
        }
    }
    
    /**
     * Handle successful login
     * 
     * @param string $user_login Username
     * @param \WP_User $user User object
     */
    public function handleSuccessfulLogin(string $user_login, \WP_User $user): void {
        $this->logSecurityEvent('login_success', [
            'username' => $user_login,
            'user_id' => $user->ID,
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    }
    
    /**
     * Log user login for security monitoring
     */
    public function logUserLogin(string $user_login, \WP_User $user): void {
        $this->handleSuccessfulLogin($user_login, $user);
    }
    
    /**
     * Log failed login for security monitoring
     */
    public function logFailedLogin(string $username): void {
        $this->handleFailedLogin($username);
    }
    
    /**
     * Add security headers
     */
    public function addSecurityHeaders(): void {
        // Prevent clickjacking
        header('X-Frame-Options: SAMEORIGIN');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy (basic)
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
    }
    
    /**
     * Check if file contains malicious content
     * 
     * @param string $filePath Path to file
     * @return bool True if malicious content found
     */
    private function containsMaliciousContent(string $filePath): bool {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $content = file_get_contents($filePath, false, null, 0, 8192); // Read first 8KB
        
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode/i',
            '/shell_exec/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru/i',
            '/file_get_contents/i',
            '/file_put_contents/i',
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function getClientIp(): string {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Block IP temporarily
     * 
     * @param string $ip IP address to block
     * @param int $duration Duration in seconds
     */
    private function blockIpTemporarily(string $ip, int $duration): void {
        $blockedIps = get_option('seo_forge_blocked_ips', []);
        $blockedIps[$ip] = time() + $duration;
        
        update_option('seo_forge_blocked_ips', $blockedIps);
        
        $this->logSecurityEvent('ip_blocked', [
            'ip' => $ip,
            'duration' => $duration,
            'expires' => time() + $duration,
        ]);
    }
    
    /**
     * Check if IP is blocked
     * 
     * @param string $ip IP address to check
     * @return bool True if blocked
     */
    public function isIpBlocked(string $ip = ''): bool {
        if (empty($ip)) {
            $ip = $this->getClientIp();
        }
        
        $blockedIps = get_option('seo_forge_blocked_ips', []);
        
        if (isset($blockedIps[$ip])) {
            if ($blockedIps[$ip] > time()) {
                return true;
            } else {
                // Remove expired block
                unset($blockedIps[$ip]);
                update_option('seo_forge_blocked_ips', $blockedIps);
            }
        }
        
        return false;
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event type
     * @param array $data Event data
     */
    private function logSecurityEvent(string $event, array $data): void {
        $this->securityEvents[] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time(),
        ];
        
        $this->logger->warning("Security event: {$event}", $data);
        
        // Store in database for analysis
        $this->storeSecurityEvent($event, $data);
    }
    
    /**
     * Store security event in database
     * 
     * @param string $event Event type
     * @param array $data Event data
     */
    private function storeSecurityEvent(string $event, array $data): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_security_events';
        
        // Create table if it doesn't exist
        $this->createSecurityEventsTable();
        
        $wpdb->insert(
            $table_name,
            [
                'event_type' => $event,
                'event_data' => json_encode($data),
                'ip_address' => $this->getClientIp(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Create security events table
     */
    private function createSecurityEventsTable(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_security_events';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY ip_address (ip_address),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Clean up old rate limit entries
     */
    public function cleanupRateLimits(): void {
        $now = time();
        
        foreach ($this->rateLimits as $key => $timestamps) {
            $this->rateLimits[$key] = array_filter(
                $timestamps,
                function($timestamp) use ($now) {
                    return ($now - $timestamp) < 3600; // Keep last hour
                }
            );
            
            if (empty($this->rateLimits[$key])) {
                unset($this->rateLimits[$key]);
            }
        }
    }
    
    /**
     * Get security events
     * 
     * @return array Security events
     */
    public function getSecurityEvents(): array {
        return $this->securityEvents;
    }
}