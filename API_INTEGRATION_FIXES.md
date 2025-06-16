# SEO-Forge Professional Plugin - API Integration Fixes

## Overview
This document outlines the comprehensive API integration fixes applied to the SEO-Forge Professional WordPress plugin to properly connect with the SEOForge MCP Server backend at https://seo-forge.bitebase.app.

## Changes Made

### 1. Updated API Endpoints
Based on the SEOForge MCP Server backend-express structure, the plugin now uses the correct API endpoints:

#### Content Generation Endpoints:
- `/api/blog-generator/generate` (Legacy API endpoint)
- `/universal-mcp/generate-content` (Universal MCP endpoint)
- `/universal-mcp/generate-blog-with-images` (Blog with images)
- `/mcp/tools/execute` (Direct MCP tool execution)

#### SEO Analysis Endpoints:
- `/api/seo-analyzer/analyze` (Legacy API endpoint)
- `/universal-mcp/analyze-seo` (Universal MCP endpoint)
- `/mcp/tools/execute` (Direct MCP tool execution)

#### Image Generation Endpoints:
- `/api/flux-image-gen/generate` (Legacy API endpoint)
- `/universal-mcp/generate-image` (Universal MCP endpoint)
- `/universal-mcp/generate-flux-image` (Flux image generation)
- `/mcp/tools/execute` (Direct MCP tool execution)

#### Health Check Endpoints:
- `/health`
- `/mcp/status`
- `/universal-mcp/status`

### 2. Enhanced API Request Structure

#### Improved Request Headers:
```php
'headers' => [
    'Content-Type' => 'application/json',
    'User-Agent' => 'SEO-Forge-WordPress-Plugin/2.0.1',
    'Accept' => 'application/json',
    'X-Plugin-Version' => '2.0.1',
    'X-WordPress-Site' => home_url(),
    'X-Client-ID' => 'wordpress-plugin'
]
```

#### Proper Request Body Format:
- Keywords converted to arrays when needed
- Language codes properly formatted (e.g., 'en', 'th')
- Length mapping to API format ('short', 'medium', 'long')
- Content type specification ('blog', 'article', etc.)

### 3. New API Functions Added

#### `generate_seo_forge_content()` - Enhanced
- Multiple endpoint fallback system
- Proper request body formatting based on endpoint type
- Enhanced error logging with response details
- Increased timeout for complex operations

#### `analyze_content_with_api()` - New
- SEO analysis using MCP server
- Multiple response format handling
- Fallback to local analysis if API fails

#### `generate_image_with_api()` - New
- AI image generation using Flux models
- Multiple image response format handling
- Professional style defaults

#### `generate_blog_with_image()` - New
- Combined blog and image generation
- Comprehensive result structure
- SEO score and suggestions included

### 4. New AJAX Handlers

#### `ajax_generate_image()`
- Standalone image generation
- Style and size customization
- Proper error handling

#### `ajax_generate_blog_with_image()`
- Blog content with integrated images
- SEO analysis included
- Word count and suggestions

#### `ajax_health_check()`
- API connectivity testing
- Multiple endpoint status checking
- Comprehensive diagnostic information

### 5. Enhanced Error Handling

#### Improved Logging:
- Detailed API response logging
- HTTP status code tracking
- Response body inspection for debugging

#### Fallback Mechanisms:
- API failure → OpenAI fallback → Local generation
- Multiple endpoint attempts before failure
- Graceful degradation of features

### 6. Response Format Handling

The plugin now handles multiple response formats from the MCP server:

```php
// Handle different response formats
if (isset($data['content'])) {
    return $data['content'];
} elseif (isset($data['result']['content'])) {
    return $data['result']['content'];
} elseif (isset($data['data']['content'])) {
    return $data['data']['content'];
}
```

## API Requirements Compliance

### Content Generation Request Format:
```json
{
    "topic": "string",
    "keywords": ["array", "of", "strings"],
    "length": "short|medium|long",
    "tone": "professional",
    "language": "en|th|etc",
    "content_type": "blog_post",
    "include_images": true,
    "image_count": 3,
    "image_style": "professional"
}
```

### SEO Analysis Request Format:
```json
{
    "content": "string",
    "keywords": ["array", "of", "strings"],
    "url": "optional_url"
}
```

### Image Generation Request Format:
```json
{
    "prompt": "string",
    "style": "professional",
    "size": "1024x1024",
    "count": 1
}
```

## Testing and Verification

### Health Check Function:
The new `ajax_health_check()` function tests:
1. API endpoint availability
2. Response format validation
3. Authentication status
4. Service connectivity

### Usage:
```javascript
// Test API connectivity
jQuery.post(ajaxurl, {
    action: 'seo_forge_health_check',
    nonce: seo_forge_ajax.nonce
}, function(response) {
    console.log('API Health:', response.data);
});
```

## Installation Instructions

1. **Backup Current Plugin**: Always backup before updating
2. **Upload New Version**: Replace the existing plugin file
3. **Test API Connection**: Use the health check function
4. **Verify Functionality**: Test content generation features

## Troubleshooting

### Common Issues:

1. **API Connection Failures**:
   - Check server connectivity to https://seo-forge.bitebase.app
   - Verify SSL certificate validity
   - Check firewall/proxy settings

2. **Authentication Errors**:
   - Ensure proper headers are being sent
   - Verify API key configuration (if required)

3. **Response Format Issues**:
   - Check error logs for detailed API responses
   - Verify endpoint compatibility

### Debug Mode:
Enable WordPress debug logging to see detailed API interaction logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Future Enhancements

1. **Caching System**: Implement response caching for better performance
2. **Rate Limiting**: Add client-side rate limiting for API calls
3. **Batch Operations**: Support for bulk content generation
4. **Advanced Analytics**: Enhanced SEO scoring and suggestions

## Support

For technical support or API-related issues:
1. Check WordPress error logs
2. Use the health check function for diagnostics
3. Review API response logs for detailed error information

## Version History

- **v2.0.1**: Complete API integration overhaul
  - Updated all API endpoints
  - Enhanced error handling
  - Added image generation support
  - Improved SEO analysis integration
  - Added comprehensive health checking

---

*This documentation reflects the current state of the SEO-Forge Professional plugin API integration as of the latest update.*