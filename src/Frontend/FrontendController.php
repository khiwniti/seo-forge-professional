<?php
declare(strict_types=1);

namespace SEOForge\Frontend;

use SEOForge\Services\Logger;
use SEOForge\Security\SecurityManager;
use Psr\Log\LoggerInterface;

/**
 * Frontend Controller
 * 
 * Manages all frontend-related functionality including asset enqueuing,
 * SEO meta tags, structured data, and public-facing features.
 * 
 * @package SEOForge\Frontend
 * @since 2.0.0
 */
class FrontendController {
    
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
     * Initialize frontend controller
     */
    public function init(): void {
        $this->setupHooks();
    }
    
    /**
     * Setup frontend hooks
     */
    private function setupHooks(): void {
        // Asset hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets'], 10);
        
        // SEO hooks
        add_action('wp_head', [$this, 'addSeoMetaTags'], 1);
        add_action('wp_head', [$this, 'addStructuredData'], 5);
        add_action('wp_head', [$this, 'addOpenGraphTags'], 10);
        add_action('wp_head', [$this, 'addTwitterCardTags'], 15);
        
        // Content hooks
        add_filter('the_content', [$this, 'enhanceContent'], 10);
        add_filter('the_excerpt', [$this, 'enhanceExcerpt'], 10);
        
        // Performance hooks
        add_action('wp_footer', [$this, 'addPerformanceTracking'], 100);
        
        // AJAX hooks for public actions
        add_action('wp_ajax_nopriv_seo_forge_track_event', [$this, 'handleTrackEvent']);
        add_action('wp_ajax_seo_forge_track_event', [$this, 'handleTrackEvent']);
        
        // Shortcode hooks
        add_action('init', [$this, 'registerShortcodes']);
        
        // Widget hooks
        add_action('widgets_init', [$this, 'registerWidgets']);
        
        // RSS hooks
        add_action('rss2_head', [$this, 'addRssMetaTags']);
        add_action('atom_head', [$this, 'addAtomMetaTags']);
        
        // Sitemap hooks
        add_action('init', [$this, 'initSitemap']);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueAssets(): void {
        // Enqueue frontend CSS
        wp_enqueue_style(
            'seo-forge-frontend',
            SEO_FORGE_URL . 'assets/css/frontend.css',
            [],
            \SEOForge\PLUGIN_VERSION
        );
        
        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'seo-forge-frontend',
            SEO_FORGE_URL . 'assets/js/frontend.js',
            ['jquery'],
            \SEOForge\PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('seo-forge-frontend', 'seoForgeFrontend', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('seo-forge/v1/'),
            'nonce' => $this->security->generateCsrfToken('frontend'),
            'trackingEnabled' => $this->isTrackingEnabled(),
        ]);
    }
    
    /**
     * Add SEO meta tags
     */
    public function addSeoMetaTags(): void {
        if (is_admin() || is_feed() || is_robots() || is_trackback()) {
            return;
        }
        
        global $post;
        
        // Meta description
        $meta_description = $this->getMetaDescription();
        if ($meta_description) {
            printf(
                '<meta name="description" content="%s" />' . "\n",
                esc_attr($meta_description)
            );
        }
        
        // Meta keywords
        $meta_keywords = $this->getMetaKeywords();
        if ($meta_keywords) {
            printf(
                '<meta name="keywords" content="%s" />' . "\n",
                esc_attr($meta_keywords)
            );
        }
        
        // Canonical URL
        $canonical_url = $this->getCanonicalUrl();
        if ($canonical_url) {
            printf(
                '<link rel="canonical" href="%s" />' . "\n",
                esc_url($canonical_url)
            );
        }
        
        // Robots meta
        $robots = $this->getRobotsDirectives();
        if ($robots) {
            printf(
                '<meta name="robots" content="%s" />' . "\n",
                esc_attr($robots)
            );
        }
        
        // Author meta
        if (is_single() && $post) {
            $author = get_the_author_meta('display_name', $post->post_author);
            if ($author) {
                printf(
                    '<meta name="author" content="%s" />' . "\n",
                    esc_attr($author)
                );
            }
        }
        
        // Language meta
        $language = get_locale();
        printf(
            '<meta name="language" content="%s" />' . "\n",
            esc_attr($language)
        );
        
        // Generator meta (plugin identification)
        printf(
            '<meta name="generator" content="SEO Forge %s" />' . "\n",
            esc_attr(\SEOForge\PLUGIN_VERSION)
        );
    }
    
    /**
     * Add structured data
     */
    public function addStructuredData(): void {
        $structured_data = $this->getStructuredData();
        
        if (!empty($structured_data)) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo "\n" . '</script>' . "\n";
        }
    }
    
    /**
     * Add Open Graph tags
     */
    public function addOpenGraphTags(): void {
        if (is_admin() || is_feed() || is_robots() || is_trackback()) {
            return;
        }
        
        // Basic Open Graph tags
        printf(
            '<meta property="og:site_name" content="%s" />' . "\n",
            esc_attr(get_bloginfo('name'))
        );
        
        printf(
            '<meta property="og:locale" content="%s" />' . "\n",
            esc_attr(str_replace('-', '_', get_locale()))
        );
        
        // Page-specific Open Graph tags
        if (is_front_page()) {
            $this->addFrontPageOpenGraph();
        } elseif (is_single() || is_page()) {
            $this->addPostOpenGraph();
        } elseif (is_category() || is_tag() || is_tax()) {
            $this->addArchiveOpenGraph();
        }
    }
    
    /**
     * Add Twitter Card tags
     */
    public function addTwitterCardTags(): void {
        if (is_admin() || is_feed() || is_robots() || is_trackback()) {
            return;
        }
        
        // Twitter Card type
        $card_type = $this->getTwitterCardType();
        printf(
            '<meta name="twitter:card" content="%s" />' . "\n",
            esc_attr($card_type)
        );
        
        // Twitter site
        $twitter_site = $this->getTwitterSite();
        if ($twitter_site) {
            printf(
                '<meta name="twitter:site" content="%s" />' . "\n",
                esc_attr($twitter_site)
            );
        }
        
        // Twitter creator
        $twitter_creator = $this->getTwitterCreator();
        if ($twitter_creator) {
            printf(
                '<meta name="twitter:creator" content="%s" />' . "\n",
                esc_attr($twitter_creator)
            );
        }
        
        // Twitter title and description
        $title = $this->getPageTitle();
        $description = $this->getMetaDescription();
        
        if ($title) {
            printf(
                '<meta name="twitter:title" content="%s" />' . "\n",
                esc_attr($title)
            );
        }
        
        if ($description) {
            printf(
                '<meta name="twitter:description" content="%s" />' . "\n",
                esc_attr($description)
            );
        }
        
        // Twitter image
        $image = $this->getFeaturedImage();
        if ($image) {
            printf(
                '<meta name="twitter:image" content="%s" />' . "\n",
                esc_url($image)
            );
        }
    }
    
    /**
     * Enhance content
     */
    public function enhanceContent(string $content): string {
        if (is_admin() || is_feed()) {
            return $content;
        }
        
        // Add reading time
        $content = $this->addReadingTime($content);
        
        // Add social sharing buttons
        $content = $this->addSocialSharing($content);
        
        // Add related posts
        $content = $this->addRelatedPosts($content);
        
        return $content;
    }
    
    /**
     * Enhance excerpt
     */
    public function enhanceExcerpt(string $excerpt): string {
        // Add SEO-optimized excerpt enhancements
        return $excerpt;
    }
    
    /**
     * Add performance tracking
     */
    public function addPerformanceTracking(): void {
        if (!$this->isTrackingEnabled()) {
            return;
        }
        
        ?>
        <script>
        (function() {
            // Performance tracking code
            if ('performance' in window) {
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        var perfData = performance.getEntriesByType('navigation')[0];
                        if (perfData) {
                            // Send performance data to analytics
                            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'seo_forge_track_event',
                                    event_type: 'performance',
                                    load_time: perfData.loadEventEnd - perfData.loadEventStart,
                                    dom_ready: perfData.domContentLoadedEventEnd - perfData.domContentLoadedEventStart,
                                    _wpnonce: '<?php echo esc_js($this->security->generateCsrfToken('frontend')); ?>'
                                })
                            });
                        }
                    }, 1000);
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Handle track event AJAX
     */
    public function handleTrackEvent(): void {
        if (!$this->security->checkRateLimit('track_event', 100, 3600)) {
            wp_die('Rate limit exceeded', 429);
        }
        
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $event_data = $_POST;
        
        // Remove sensitive data
        unset($event_data['action'], $event_data['_wpnonce']);
        
        // Store event data
        $this->storeAnalyticsEvent($event_type, $event_data);
        
        wp_send_json_success();
    }
    
    /**
     * Register shortcodes
     */
    public function registerShortcodes(): void {
        add_shortcode('seo_forge_content', [$this, 'renderContentShortcode']);
        add_shortcode('seo_forge_analytics', [$this, 'renderAnalyticsShortcode']);
        add_shortcode('seo_forge_keywords', [$this, 'renderKeywordsShortcode']);
    }
    
    /**
     * Register widgets
     */
    public function registerWidgets(): void {
        // Widget registration will be implemented
    }
    
    /**
     * Add RSS meta tags
     */
    public function addRssMetaTags(): void {
        printf(
            '<generator>SEO Forge %s</generator>' . "\n",
            esc_html(\SEOForge\PLUGIN_VERSION)
        );
    }
    
    /**
     * Add Atom meta tags
     */
    public function addAtomMetaTags(): void {
        printf(
            '<generator uri="%s" version="%s">SEO Forge</generator>' . "\n",
            esc_url('https://seo-forge.bitebase.app'),
            esc_attr(\SEOForge\PLUGIN_VERSION)
        );
    }
    
    /**
     * Initialize sitemap
     */
    public function initSitemap(): void {
        // Sitemap functionality will be implemented
    }
    
    /**
     * Get meta description
     */
    private function getMetaDescription(): string {
        global $post;
        
        if (is_single() || is_page()) {
            // Check for custom meta description
            $custom_description = get_post_meta($post->ID, '_seo_forge_meta_description', true);
            if ($custom_description) {
                return $custom_description;
            }
            
            // Use excerpt or trimmed content
            if ($post->post_excerpt) {
                return wp_trim_words($post->post_excerpt, 25);
            }
            
            return wp_trim_words(strip_tags($post->post_content), 25);
        }
        
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term && $term->description) {
                return wp_trim_words($term->description, 25);
            }
        }
        
        if (is_home() || is_front_page()) {
            return get_bloginfo('description');
        }
        
        return '';
    }
    
    /**
     * Get meta keywords
     */
    private function getMetaKeywords(): string {
        global $post;
        
        if (is_single() || is_page()) {
            $keywords = get_post_meta($post->ID, '_seo_forge_focus_keyword', true);
            if ($keywords) {
                return $keywords;
            }
            
            // Get tags as keywords
            $tags = get_the_tags($post->ID);
            if ($tags) {
                return implode(', ', wp_list_pluck($tags, 'name'));
            }
        }
        
        return '';
    }
    
    /**
     * Get canonical URL
     */
    private function getCanonicalUrl(): string {
        if (is_single() || is_page()) {
            return get_permalink();
        }
        
        if (is_category() || is_tag() || is_tax()) {
            return get_term_link(get_queried_object());
        }
        
        if (is_home()) {
            return home_url('/');
        }
        
        return '';
    }
    
    /**
     * Get robots directives
     */
    private function getRobotsDirectives(): string {
        $directives = [];
        
        if (is_search() || is_404()) {
            $directives[] = 'noindex';
            $directives[] = 'nofollow';
        } elseif (is_archive() && !is_category() && !is_tag()) {
            $directives[] = 'noindex';
            $directives[] = 'follow';
        } else {
            $directives[] = 'index';
            $directives[] = 'follow';
        }
        
        return implode(', ', $directives);
    }
    
    /**
     * Get structured data
     */
    private function getStructuredData(): array {
        $data = [];
        
        if (is_single()) {
            $data = $this->getArticleStructuredData();
        } elseif (is_page()) {
            $data = $this->getWebPageStructuredData();
        } elseif (is_home() || is_front_page()) {
            $data = $this->getWebsiteStructuredData();
        }
        
        return apply_filters('seo_forge_structured_data', $data);
    }
    
    /**
     * Get article structured data
     */
    private function getArticleStructuredData(): array {
        global $post;
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => get_the_title(),
            'description' => $this->getMetaDescription(),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->getSiteLogo(),
                ],
            ],
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'mainEntityOfPage' => get_permalink(),
            'image' => $this->getFeaturedImage(),
        ];
    }
    
    /**
     * Get webpage structured data
     */
    private function getWebPageStructuredData(): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => get_the_title(),
            'description' => $this->getMetaDescription(),
            'url' => get_permalink(),
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
            ],
        ];
    }
    
    /**
     * Get website structured data
     */
    private function getWebsiteStructuredData(): array {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }
    
    /**
     * Add front page Open Graph
     */
    private function addFrontPageOpenGraph(): void {
        printf(
            '<meta property="og:type" content="website" />' . "\n"
        );
        
        printf(
            '<meta property="og:title" content="%s" />' . "\n",
            esc_attr(get_bloginfo('name'))
        );
        
        printf(
            '<meta property="og:description" content="%s" />' . "\n",
            esc_attr(get_bloginfo('description'))
        );
        
        printf(
            '<meta property="og:url" content="%s" />' . "\n",
            esc_url(home_url('/'))
        );
    }
    
    /**
     * Add post Open Graph
     */
    private function addPostOpenGraph(): void {
        global $post;
        
        printf(
            '<meta property="og:type" content="article" />' . "\n"
        );
        
        printf(
            '<meta property="og:title" content="%s" />' . "\n",
            esc_attr(get_the_title())
        );
        
        $description = $this->getMetaDescription();
        if ($description) {
            printf(
                '<meta property="og:description" content="%s" />' . "\n",
                esc_attr($description)
            );
        }
        
        printf(
            '<meta property="og:url" content="%s" />' . "\n",
            esc_url(get_permalink())
        );
        
        $image = $this->getFeaturedImage();
        if ($image) {
            printf(
                '<meta property="og:image" content="%s" />' . "\n",
                esc_url($image)
            );
        }
        
        printf(
            '<meta property="article:published_time" content="%s" />' . "\n",
            esc_attr(get_the_date('c'))
        );
        
        printf(
            '<meta property="article:modified_time" content="%s" />' . "\n",
            esc_attr(get_the_modified_date('c'))
        );
        
        printf(
            '<meta property="article:author" content="%s" />' . "\n",
            esc_attr(get_the_author_meta('display_name', $post->post_author))
        );
    }
    
    /**
     * Add archive Open Graph
     */
    private function addArchiveOpenGraph(): void {
        $term = get_queried_object();
        
        printf(
            '<meta property="og:type" content="website" />' . "\n"
        );
        
        printf(
            '<meta property="og:title" content="%s" />' . "\n",
            esc_attr($term->name)
        );
        
        if ($term->description) {
            printf(
                '<meta property="og:description" content="%s" />' . "\n",
                esc_attr(wp_trim_words($term->description, 25))
            );
        }
        
        printf(
            '<meta property="og:url" content="%s" />' . "\n",
            esc_url(get_term_link($term))
        );
    }
    
    /**
     * Get page title
     */
    private function getPageTitle(): string {
        if (is_single() || is_page()) {
            return get_the_title();
        }
        
        if (is_category() || is_tag() || is_tax()) {
            return single_term_title('', false);
        }
        
        if (is_home() || is_front_page()) {
            return get_bloginfo('name');
        }
        
        return wp_get_document_title();
    }
    
    /**
     * Get featured image
     */
    private function getFeaturedImage(): string {
        if (is_single() || is_page()) {
            $image = get_the_post_thumbnail_url(null, 'large');
            if ($image) {
                return $image;
            }
        }
        
        return $this->getSiteLogo();
    }
    
    /**
     * Get site logo
     */
    private function getSiteLogo(): string {
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo = wp_get_attachment_image_url($custom_logo_id, 'full');
            if ($logo) {
                return $logo;
            }
        }
        
        return '';
    }
    
    /**
     * Get Twitter card type
     */
    private function getTwitterCardType(): string {
        if ($this->getFeaturedImage()) {
            return 'summary_large_image';
        }
        
        return 'summary';
    }
    
    /**
     * Get Twitter site
     */
    private function getTwitterSite(): string {
        // This would come from plugin settings
        return '';
    }
    
    /**
     * Get Twitter creator
     */
    private function getTwitterCreator(): string {
        if (is_single()) {
            global $post;
            $twitter = get_the_author_meta('twitter', $post->post_author);
            if ($twitter) {
                return '@' . ltrim($twitter, '@');
            }
        }
        
        return '';
    }
    
    /**
     * Add reading time
     */
    private function addReadingTime(string $content): string {
        if (!is_single()) {
            return $content;
        }
        
        $word_count = str_word_count(strip_tags($content));
        $reading_time = ceil($word_count / 200); // Average reading speed
        
        $reading_time_html = sprintf(
            '<div class="seo-forge-reading-time">%s</div>',
            sprintf(
                _n('%d minute read', '%d minutes read', $reading_time, 'seo-forge'),
                $reading_time
            )
        );
        
        return $reading_time_html . $content;
    }
    
    /**
     * Add social sharing
     */
    private function addSocialSharing(string $content): string {
        if (!is_single()) {
            return $content;
        }
        
        $sharing_html = '<div class="seo-forge-social-sharing">';
        $sharing_html .= '<h4>' . __('Share this post:', 'seo-forge') . '</h4>';
        $sharing_html .= '<div class="sharing-buttons">';
        
        $url = urlencode(get_permalink());
        $title = urlencode(get_the_title());
        
        // Facebook
        $sharing_html .= sprintf(
            '<a href="https://www.facebook.com/sharer/sharer.php?u=%s" target="_blank" rel="noopener">%s</a>',
            $url,
            __('Facebook', 'seo-forge')
        );
        
        // Twitter
        $sharing_html .= sprintf(
            '<a href="https://twitter.com/intent/tweet?url=%s&text=%s" target="_blank" rel="noopener">%s</a>',
            $url,
            $title,
            __('Twitter', 'seo-forge')
        );
        
        // LinkedIn
        $sharing_html .= sprintf(
            '<a href="https://www.linkedin.com/sharing/share-offsite/?url=%s" target="_blank" rel="noopener">%s</a>',
            $url,
            __('LinkedIn', 'seo-forge')
        );
        
        $sharing_html .= '</div></div>';
        
        return $content . $sharing_html;
    }
    
    /**
     * Add related posts
     */
    private function addRelatedPosts(string $content): string {
        if (!is_single()) {
            return $content;
        }
        
        global $post;
        
        $related_posts = get_posts([
            'post_type' => $post->post_type,
            'posts_per_page' => 3,
            'post__not_in' => [$post->ID],
            'category__in' => wp_get_post_categories($post->ID),
            'orderby' => 'rand',
        ]);
        
        if (empty($related_posts)) {
            return $content;
        }
        
        $related_html = '<div class="seo-forge-related-posts">';
        $related_html .= '<h4>' . __('Related Posts:', 'seo-forge') . '</h4>';
        $related_html .= '<div class="related-posts-grid">';
        
        foreach ($related_posts as $related_post) {
            $related_html .= sprintf(
                '<div class="related-post"><a href="%s">%s</a></div>',
                esc_url(get_permalink($related_post->ID)),
                esc_html($related_post->post_title)
            );
        }
        
        $related_html .= '</div></div>';
        
        return $content . $related_html;
    }
    
    /**
     * Check if tracking is enabled
     */
    private function isTrackingEnabled(): bool {
        $settings = get_option('seo_forge_settings', []);
        return $settings['general']['enable_analytics'] ?? false;
    }
    
    /**
     * Store analytics event
     */
    private function storeAnalyticsEvent(string $event_type, array $event_data): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'seo_forge_analytics';
        
        $wpdb->insert(
            $table_name,
            [
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'metric_type' => $event_type,
                'metric_value' => $event_data['load_time'] ?? 0,
                'date_recorded' => current_time('mysql'),
                'source' => 'frontend',
                'additional_data' => json_encode($event_data),
            ],
            ['%s', '%s', '%f', '%s', '%s', '%s']
        );
    }
    
    /**
     * Render content shortcode
     */
    public function renderContentShortcode(array $atts): string {
        // Shortcode implementation
        return '';
    }
    
    /**
     * Render analytics shortcode
     */
    public function renderAnalyticsShortcode(array $atts): string {
        // Shortcode implementation
        return '';
    }
    
    /**
     * Render keywords shortcode
     */
    public function renderKeywordsShortcode(array $atts): string {
        // Shortcode implementation
        return '';
    }
}