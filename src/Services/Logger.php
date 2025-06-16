<?php
declare(strict_types=1);

namespace SEOForge\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\InvalidArgumentException;

/**
 * Professional Logger Service
 * 
 * PSR-3 compatible logger with WordPress integration, context support,
 * and multiple output channels for comprehensive debugging and monitoring.
 * 
 * @package SEOForge\Services
 * @since 2.0.0
 */
class Logger implements LoggerInterface {
    
    /**
     * Logger name/channel
     */
    private string $name;
    
    /**
     * Log level hierarchy
     */
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT     => 1,
        LogLevel::CRITICAL  => 2,
        LogLevel::ERROR     => 3,
        LogLevel::WARNING   => 4,
        LogLevel::NOTICE    => 5,
        LogLevel::INFO      => 6,
        LogLevel::DEBUG     => 7,
    ];
    
    /**
     * Minimum log level to process
     */
    private string $minLevel;
    
    /**
     * Log handlers
     */
    private array $handlers = [];
    
    /**
     * Constructor
     * 
     * @param string $name Logger name/channel
     * @param string $minLevel Minimum log level
     */
    public function __construct(string $name = 'seo-forge', string $minLevel = LogLevel::INFO) {
        $this->name = $name;
        $this->minLevel = $minLevel;
        
        $this->initializeHandlers();
    }
    
    /**
     * System is unusable.
     */
    public function emergency(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }
    
    /**
     * Action must be taken immediately.
     */
    public function alert(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ALERT, $message, $context);
    }
    
    /**
     * Critical conditions.
     */
    public function critical(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }
    
    /**
     * Runtime errors that do not require immediate action.
     */
    public function error(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::ERROR, $message, $context);
    }
    
    /**
     * Exceptional occurrences that are not errors.
     */
    public function warning(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::WARNING, $message, $context);
    }
    
    /**
     * Normal but significant events.
     */
    public function notice(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::NOTICE, $message, $context);
    }
    
    /**
     * Interesting events.
     */
    public function info(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::INFO, $message, $context);
    }
    
    /**
     * Detailed debug information.
     */
    public function debug(string|\Stringable $message, array $context = []): void {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
    
    /**
     * Logs with an arbitrary level.
     */
    public function log($level, string|\Stringable $message, array $context = []): void {
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $record = $this->createLogRecord($level, $message, $context);
        
        foreach ($this->handlers as $handler) {
            $handler($record);
        }
    }
    
    /**
     * Check if message should be logged based on level
     */
    private function shouldLog(string $level): bool {
        if (!isset(self::LOG_LEVELS[$level])) {
            throw new InvalidArgumentException("Invalid log level: {$level}");
        }
        
        return self::LOG_LEVELS[$level] <= self::LOG_LEVELS[$this->minLevel];
    }
    
    /**
     * Create a log record
     */
    private function createLogRecord(string $level, string|\Stringable $message, array $context): array {
        $record = [
            'channel' => $this->name,
            'level' => $level,
            'level_name' => strtoupper($level),
            'message' => $this->interpolate((string) $message, $context),
            'context' => $context,
            'datetime' => new \DateTimeImmutable(),
            'extra' => $this->getExtraData(),
        ];
        
        return $record;
    }
    
    /**
     * Interpolate context values into message placeholders
     */
    private function interpolate(string $message, array $context): string {
        $replace = [];
        
        foreach ($context as $key => $val) {
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            } elseif (is_array($val) || is_object($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * Get extra data for log record
     */
    private function getExtraData(): array {
        $extra = [
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
        
        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user->ID) {
                $extra['user_id'] = $user->ID;
                $extra['user_login'] = $user->user_login;
            }
        }
        
        if (isset($_SERVER['REQUEST_URI'])) {
            $extra['request_uri'] = $_SERVER['REQUEST_URI'];
        }
        
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $extra['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        
        return $extra;
    }
    
    /**
     * Initialize log handlers
     */
    private function initializeHandlers(): void {
        // WordPress debug log handler
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $this->handlers[] = [$this, 'wordpressLogHandler'];
        }
        
        // Custom log file handler
        $this->handlers[] = [$this, 'fileLogHandler'];
        
        // Database log handler for critical errors
        if (in_array($this->minLevel, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR])) {
            $this->handlers[] = [$this, 'databaseLogHandler'];
        }
        
        // Admin notice handler for development
        if (defined('WP_DEBUG') && WP_DEBUG && is_admin()) {
            $this->handlers[] = [$this, 'adminNoticeHandler'];
        }
    }
    
    /**
     * WordPress debug log handler
     */
    private function wordpressLogHandler(array $record): void {
        $message = $this->formatLogMessage($record);
        error_log($message);
    }
    
    /**
     * Custom file log handler
     */
    private function fileLogHandler(array $record): void {
        $logDir = WP_CONTENT_DIR . '/uploads/seo-forge-logs';
        
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
            
            // Create .htaccess to protect log files
            $htaccess = $logDir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
        }
        
        $logFile = $logDir . '/seo-forge-' . date('Y-m-d') . '.log';
        $message = $this->formatLogMessage($record) . "\n";
        
        file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Database log handler for critical errors
     */
    private function databaseLogHandler(array $record): void {
        global $wpdb;
        
        if (!$wpdb || in_array($record['level'], [LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE])) {
            return;
        }
        
        $table_name = $wpdb->prefix . 'seo_forge_logs';
        
        // Create table if it doesn't exist
        $this->createLogTable();
        
        $wpdb->insert(
            $table_name,
            [
                'level' => $record['level'],
                'message' => $record['message'],
                'context' => json_encode($record['context']),
                'extra' => json_encode($record['extra']),
                'created_at' => $record['datetime']->format('Y-m-d H:i:s'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Admin notice handler for development
     */
    private function adminNoticeHandler(array $record): void {
        if (!in_array($record['level'], [LogLevel::ERROR, LogLevel::CRITICAL, LogLevel::ALERT, LogLevel::EMERGENCY])) {
            return;
        }
        
        add_action('admin_notices', function() use ($record) {
            $class = 'notice notice-error';
            $message = sprintf(
                '[%s] %s: %s',
                $this->name,
                strtoupper($record['level']),
                $record['message']
            );
            
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
    
    /**
     * Format log message
     */
    private function formatLogMessage(array $record): string {
        $format = '[%s] %s.%s: %s';
        
        $message = sprintf(
            $format,
            $record['datetime']->format('Y-m-d H:i:s'),
            $record['channel'],
            $record['level_name'],
            $record['message']
        );
        
        if (!empty($record['context'])) {
            $message .= ' ' . json_encode($record['context']);
        }
        
        return $message;
    }
    
    /**
     * Create log table in database
     */
    private function createLogTable(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            extra longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Set minimum log level
     */
    public function setMinLevel(string $level): void {
        if (!isset(self::LOG_LEVELS[$level])) {
            throw new InvalidArgumentException("Invalid log level: {$level}");
        }
        
        $this->minLevel = $level;
    }
    
    /**
     * Get minimum log level
     */
    public function getMinLevel(): string {
        return $this->minLevel;
    }
    
    /**
     * Add custom handler
     */
    public function addHandler(callable $handler): void {
        $this->handlers[] = $handler;
    }
    
    /**
     * Clear all handlers
     */
    public function clearHandlers(): void {
        $this->handlers = [];
    }
    
    /**
     * Get logger name
     */
    public function getName(): string {
        return $this->name;
    }
}