<?php
declare(strict_types=1);

namespace SEOForge\Core;

/**
 * Plugin Deactivator
 * 
 * Handles plugin deactivation tasks including cleanup of scheduled events,
 * temporary data removal, and graceful shutdown procedures.
 * 
 * @package SEOForge\Core
 * @since 2.0.0
 */
class Deactivator {
    
    /**
     * Plugin deactivation handler
     */
    public function deactivate(): void {
        try {
            $this->clearScheduledEvents();
            $this->cleanupTemporaryData();
            $this->flushRewriteRules();
            $this->logDeactivation();
            
        } catch (\Throwable $e) {
            // Log error but don't prevent deactivation
            if (function_exists('error_log')) {
                error_log('SEO-Forge deactivation error: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Clear all scheduled events
     */
    private function clearScheduledEvents(): void {
        $events = [
            'seo_forge_generate_content',
            'seo_forge_sync_analytics',
            'seo_forge_keyword_research',
            'seo_forge_cleanup',
            'seo_forge_security_audit',
        ];
        
        foreach ($events as $event) {
            wp_clear_scheduled_hook($event);
        }
    }
    
    /**
     * Clean up temporary data
     */
    private function cleanupTemporaryData(): void {
        // Clear transients
        $this->clearTransients();
        
        // Clear cache files
        $this->clearCacheFiles();
        
        // Clear temporary uploads
        $this->clearTemporaryUploads();
        
        // Clear expired security blocks
        $this->clearExpiredSecurityBlocks();
    }
    
    /**
     * Clear plugin transients
     */
    private function clearTransients(): void {
        global $wpdb;
        
        // Delete all SEO Forge transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_seo_forge_%',
                '_transient_timeout_seo_forge_%'
            )
        );
    }
    
    /**
     * Clear cache files
     */
    private function clearCacheFiles(): void {
        $uploadDir = wp_upload_dir();
        $cacheDir = $uploadDir['basedir'] . '/seo-forge/cache';
        
        if (is_dir($cacheDir)) {
            $this->deleteDirectoryContents($cacheDir);
        }
    }
    
    /**
     * Clear temporary uploads
     */
    private function clearTemporaryUploads(): void {
        $uploadDir = wp_upload_dir();
        $tempDir = $uploadDir['basedir'] . '/seo-forge/temp';
        
        if (is_dir($tempDir)) {
            $this->deleteDirectoryContents($tempDir);
        }
    }
    
    /**
     * Clear expired security blocks
     */
    private function clearExpiredSecurityBlocks(): void {
        $blockedIps = get_option('seo_forge_blocked_ips', []);
        $currentTime = time();
        
        foreach ($blockedIps as $ip => $expiry) {
            if ($expiry <= $currentTime) {
                unset($blockedIps[$ip]);
            }
        }
        
        update_option('seo_forge_blocked_ips', $blockedIps);
    }
    
    /**
     * Delete directory contents recursively
     * 
     * @param string $dir Directory path
     */
    private function deleteDirectoryContents(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectoryContents($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }
    
    /**
     * Flush rewrite rules
     */
    private function flushRewriteRules(): void {
        flush_rewrite_rules();
    }
    
    /**
     * Log deactivation
     */
    private function logDeactivation(): void {
        // Update deactivation timestamp
        update_option('seo_forge_deactivated_at', current_time('mysql'));
        
        // Log to file if possible
        if (function_exists('error_log')) {
            error_log('SEO-Forge plugin deactivated successfully');
        }
    }
    
    /**
     * Get deactivation status
     * 
     * @return array Deactivation status information
     */
    public static function getDeactivationStatus(): array {
        return [
            'deactivated_at' => get_option('seo_forge_deactivated_at'),
            'cleanup_completed' => true,
        ];
    }
}