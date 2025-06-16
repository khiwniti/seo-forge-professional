<?php
/**
 * Admin Dashboard Template
 * 
 * @package SEOForge
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$welcome = isset($_GET['welcome']) && $_GET['welcome'] === '1';
?>

<div class="wrap seo-forge-admin">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('SEO Forge Dashboard', 'seo-forge'); ?>
        <span class="title-count theme-count"><?php echo esc_html(\SEOForge\PLUGIN_VERSION); ?></span>
    </h1>
    
    <?php if ($welcome): ?>
    <div class="notice notice-success is-dismissible">
        <p>
            <strong><?php esc_html_e('Welcome to SEO Forge!', 'seo-forge'); ?></strong>
            <?php esc_html_e('Thank you for installing our plugin. Get started by configuring your settings.', 'seo-forge'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=seo-forge-settings')); ?>" class="button button-primary">
                <?php esc_html_e('Configure Settings', 'seo-forge'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="seo-forge-dashboard-grid">
        <!-- Quick Stats -->
        <div class="seo-forge-card">
            <h2><?php esc_html_e('Quick Stats', 'seo-forge'); ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Content Generated', 'seo-forge'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Keywords Tracked', 'seo-forge'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number">0</span>
                    <span class="stat-label"><?php esc_html_e('Templates Created', 'seo-forge'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="seo-forge-card">
            <h2><?php esc_html_e('Recent Activity', 'seo-forge'); ?></h2>
            <div class="activity-list">
                <p class="no-activity"><?php esc_html_e('No recent activity found.', 'seo-forge'); ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="seo-forge-card">
            <h2><?php esc_html_e('Quick Actions', 'seo-forge'); ?></h2>
            <div class="quick-actions">
                <a href="<?php echo esc_url(admin_url('admin.php?page=seo-forge-generator')); ?>" class="button button-primary">
                    <?php esc_html_e('Generate Content', 'seo-forge'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=seo-forge-keywords')); ?>" class="button">
                    <?php esc_html_e('Manage Keywords', 'seo-forge'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=seo-forge-analytics')); ?>" class="button">
                    <?php esc_html_e('View Analytics', 'seo-forge'); ?>
                </a>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="seo-forge-card">
            <h2><?php esc_html_e('System Status', 'seo-forge'); ?></h2>
            <div class="system-status">
                <div class="status-item">
                    <span class="status-label"><?php esc_html_e('Plugin Version:', 'seo-forge'); ?></span>
                    <span class="status-value"><?php echo esc_html(\SEOForge\PLUGIN_VERSION); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><?php esc_html_e('PHP Version:', 'seo-forge'); ?></span>
                    <span class="status-value"><?php echo esc_html(PHP_VERSION); ?></span>
                </div>
                <div class="status-item">
                    <span class="status-label"><?php esc_html_e('WordPress Version:', 'seo-forge'); ?></span>
                    <span class="status-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.seo-forge-admin {
    margin: 20px 0;
}

.seo-forge-dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.seo-forge-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.seo-forge-card h2 {
    margin: 0 0 15px 0;
    font-size: 18px;
    font-weight: 600;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #0073aa;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: #666;
    margin-top: 5px;
}

.quick-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.quick-actions .button {
    justify-content: center;
}

.system-status .status-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
}

.system-status .status-item:last-child {
    border-bottom: none;
}

.status-label {
    font-weight: 500;
}

.no-activity {
    color: #666;
    font-style: italic;
    text-align: center;
    padding: 20px 0;
}
</style>