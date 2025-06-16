<?php
declare(strict_types=1);

namespace SEOForge\Utils;

/**
 * Internationalization (i18n) Support
 * 
 * Handles plugin text domain loading and internationalization features
 * for multi-language support.
 * 
 * @package SEOForge\Utils
 * @since 2.0.0
 */
class I18n {
    
    /**
     * Text domain
     */
    private const TEXT_DOMAIN = 'seo-forge';
    
    /**
     * Load plugin text domain
     */
    public function loadTextDomain(): void {
        load_plugin_textdomain(
            self::TEXT_DOMAIN,
            false,
            dirname(SEO_FORGE_BASENAME) . '/languages'
        );
    }
    
    /**
     * Get text domain
     */
    public function getTextDomain(): string {
        return self::TEXT_DOMAIN;
    }
    
    /**
     * Get available languages
     */
    public function getAvailableLanguages(): array {
        return [
            'en' => __('English', self::TEXT_DOMAIN),
            'th' => __('Thai', self::TEXT_DOMAIN),
        ];
    }
    
    /**
     * Get current language
     */
    public function getCurrentLanguage(): string {
        return substr(get_locale(), 0, 2);
    }
    
    /**
     * Check if language is RTL
     */
    public function isRtl(string $language = ''): bool {
        if (empty($language)) {
            $language = $this->getCurrentLanguage();
        }
        
        $rtl_languages = ['ar', 'he', 'fa', 'ur'];
        return in_array($language, $rtl_languages, true);
    }
}