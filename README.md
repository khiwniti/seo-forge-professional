# SEO-Forge Professional WordPress Plugin

[![Version](https://img.shields.io/badge/version-2.0.1-blue.svg)](https://github.com/khiwniti/seo-forge-professional)
[![WordPress](https://img.shields.io/badge/wordpress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/php-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL%20v2%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A professional-grade WordPress plugin with advanced SEO capabilities, AI-powered content generation, comprehensive analytics, and enterprise-level security features. Built with modern architecture and following WordPress best practices.

## 🔄 Recent Updates (v2.0.1)
- ✅ **Fixed critical PHP syntax errors** - Resolved missing closing braces and syntax issues
- ✅ **Updated API integration** - Connected to real SEOForge MCP server endpoints
- ✅ **Enhanced error handling** - Improved fallback systems when API is unavailable
- ✅ **Thai language support** - Enhanced Thai content generation capabilities
- ✅ **Version consistency** - Fixed version mismatches across all plugin files
- ✅ **Comprehensive logging** - Added detailed debugging and error tracking
- ✅ **Production ready** - Plugin tested and ready for deployment

## 🚀 Features

### Core SEO Features
- **Advanced SEO Analysis**: Real-time content analysis with actionable suggestions
- **Meta Tag Management**: Comprehensive meta description, keywords, and Open Graph support
- **Structured Data**: Automatic JSON-LD schema markup generation
- **XML Sitemaps**: Dynamic sitemap generation and submission
- **Canonical URLs**: Automatic canonical URL management
- **Robots Meta**: Intelligent robots directive management

### AI-Powered Content Generation
- **Smart Content Creation**: AI-powered blog post and page generation
- **Multi-language Support**: English and Thai language content generation
- **SEO-Optimized Output**: Content automatically optimized for target keywords
- **Template System**: Reusable content templates with variable support
- **Quality Scoring**: Automatic content quality and readability assessment

### Analytics & Performance
- **Comprehensive Analytics**: Track pageviews, sessions, bounce rates, and more
- **Performance Monitoring**: Real-time performance tracking and optimization
- **Keyword Tracking**: Monitor keyword rankings and density
- **Content Performance**: Analyze content effectiveness and engagement
- **Custom Reports**: Generate detailed SEO and performance reports

### Security & Enterprise Features
- **Advanced Security**: Rate limiting, IP blocking, and intrusion detection
- **CSRF Protection**: Comprehensive nonce verification and token management
- **Input Sanitization**: Multi-layer input validation and output escaping
- **Audit Logging**: Detailed security event logging and monitoring
- **Role-Based Access**: Custom capabilities and role management

### Developer Features
- **Modern Architecture**: Built with SOLID principles and dependency injection
- **PSR Standards**: PSR-4 autoloading, PSR-3 logging, PSR-11 container
- **REST API**: Comprehensive REST API with authentication and rate limiting
- **Extensible Design**: Hook system for custom extensions and integrations
- **Testing Framework**: PHPUnit tests with WordPress test environment

## 📋 Requirements

### Minimum Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher (or MariaDB 10.2+)
- **Memory**: 128MB minimum (256MB recommended)
- **Disk Space**: 50MB for plugin files

### Recommended Environment
- **WordPress**: Latest stable version
- **PHP**: 8.1 or higher with OPcache enabled
- **MySQL**: 8.0 or higher
- **Memory**: 512MB or higher
- **SSL**: HTTPS enabled for security features

### Required PHP Extensions
- `json` - JSON processing
- `curl` - API communications
- `mbstring` - Multi-byte string handling
- `openssl` - Encryption and security

## 🔧 Installation

### 📦 Release Package
- **Current Version**: 2.0.1
- **Package File**: `seo-forge-professional-v2.0.1.zip` (included in this repository)
- **Size**: ~100KB
- **Last Updated**: June 16, 2025

### Manual Installation (Recommended)
1. Download `seo-forge-professional-v2.0.1.zip` from this repository
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Choose the ZIP file and click **Install Now**
5. Click **Activate** to enable the plugin

### Alternative Installation
1. Download and extract the ZIP file
2. Upload the `seo-forge-professional` folder to `/wp-content/plugins/`
3. Activate through the WordPress admin panel

### Development Setup
```bash
# Clone the repository
git clone https://github.com/seo-forge/seo-forge.git
cd seo-forge

# Install dependencies
composer install

# Build assets (if needed)
npm install && npm run build
```

## ⚙️ Configuration

### Initial Setup
1. Navigate to **SEO Forge > Settings** in your WordPress admin
2. Configure your basic settings:
   - Default language (English/Thai)
   - Content quality thresholds
   - Analytics preferences

### API Configuration
1. Go to **SEO Forge > Settings > API**
2. Configure your API keys:
   - OpenAI API key for content generation
   - Google Analytics tracking ID
   - Google Search Console integration

### Security Settings
1. Access **SEO Forge > Settings > Security**
2. Configure security options:
   - Rate limiting settings
   - IP blocking preferences
   - Security monitoring levels

## 📖 Usage Guide

### Content Generation
```php
// Generate content via REST API
POST /wp-json/seo-forge/v1/generate
{
    "type": "blog",
    "topic": "WordPress SEO Best Practices",
    "keywords": "WordPress SEO, optimization",
    "language": "en",
    "word_count": 1000
}
```

### SEO Analysis
```php
// Analyze content
POST /wp-json/seo-forge/v1/analyze
{
    "content": "Your content here...",
    "keyword": "target keyword"
}
```

### Custom Hooks
```php
// Add custom content generation filters
add_filter('seo_forge_generate_content', function($content, $params) {
    // Customize generated content
    return $content;
}, 10, 2);

// Hook into analytics events
add_action('seo_forge_analytics_tracked', function($event_data) {
    // Process analytics data
});
```

## 🧪 Development

### Plugin Structure
```
seo-forge/
├── src/                    # Modern PHP classes (PSR-4)
│   ├── Core/              # Core plugin functionality
│   ├── Admin/             # Admin interface
│   ├── Frontend/          # Public-facing features
│   ├── API/               # REST API endpoints
│   ├── Services/          # Service classes
│   ├── Security/          # Security features
│   └── Utils/             # Utility classes
├── assets/                # CSS, JS, images
├── templates/             # Template files
├── languages/             # Translation files
├── tests/                 # PHPUnit tests
├── vendor/                # Composer dependencies
├── composer.json          # Composer configuration
├── phpunit.xml           # PHPUnit configuration
├── phpstan.neon          # PHPStan configuration
└── seo-forge.php         # Main plugin file
```

### Running Tests
```bash
# Unit tests
./vendor/bin/phpunit --testsuite=unit

# Integration tests
./vendor/bin/phpunit --testsuite=integration

# All tests with coverage
composer test-coverage
```

### Code Quality
```bash
# PHP CodeSniffer
composer phpcs

# Fix coding standards
composer phpcbf

# Static analysis
composer phpstan
```

## 🔌 REST API

### Authentication
```bash
# Using WordPress nonce
curl -X GET "https://yoursite.com/wp-json/seo-forge/v1/content" \
  -H "X-WP-Nonce: your-nonce-here"

# Using application passwords (WP 5.6+)
curl -X GET "https://yoursite.com/wp-json/seo-forge/v1/content" \
  --user "username:application-password"
```

### Endpoints
- `GET /seo-forge/v1/content` - Retrieve content
- `POST /seo-forge/v1/content` - Create content
- `GET /seo-forge/v1/analytics` - Get analytics data
- `POST /seo-forge/v1/generate` - Generate content
- `POST /seo-forge/v1/analyze` - Analyze content
- `GET /seo-forge/v1/health` - Health check

## 🔒 Security

### Security Features
- **Input Validation**: All inputs are validated and sanitized
- **Output Escaping**: All outputs are properly escaped
- **CSRF Protection**: Nonce verification on all forms
- **Rate Limiting**: API and form submission rate limiting
- **SQL Injection Prevention**: Prepared statements for all queries
- **XSS Prevention**: Content Security Policy headers

### Reporting Security Issues
Please report security vulnerabilities to: security@seo-forge.bitebase.app

## 📊 Performance

### Optimization Features
- **Lazy Loading**: Components loaded only when needed
- **Caching**: Built-in caching for expensive operations
- **Database Optimization**: Efficient queries with proper indexing
- **Asset Optimization**: Minified CSS/JS with conditional loading
- **Memory Management**: Optimized memory usage and cleanup

## 🌐 Internationalization

### Supported Languages
- English (en) - Default
- Thai (th) - Full translation

### Adding Translations
1. Copy `/languages/seo-forge.pot` to `/languages/seo-forge-{locale}.po`
2. Translate strings using Poedit or similar tool
3. Generate `.mo` file
4. Place in `/languages/` directory

## 📞 Support

### Documentation
- **Plugin Documentation**: https://seo-forge.bitebase.app/docs
- **API Reference**: https://seo-forge.bitebase.app/api
- **Developer Guide**: https://seo-forge.bitebase.app/developers

### Community Support
- **GitHub Issues**: https://github.com/seo-forge/seo-forge/issues
- **WordPress Forum**: https://wordpress.org/support/plugin/seo-forge

### Professional Support
- **Email Support**: support@seo-forge.bitebase.app
- **Custom Development**: enterprise@seo-forge.bitebase.app

## 📄 License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- PSR standards for modern PHP development
- All contributors and beta testers
- Open source libraries and dependencies

---

**Made with ❤️ by the SEO-Forge Team**

For more information, visit [seo-forge.bitebase.app](https://seo-forge.bitebase.app) 