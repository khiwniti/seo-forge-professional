# SEO-Forge Plugin Installation

This document provides detailed instructions for installing and setting up the SEO-Forge WordPress plugin.

## System Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher / MariaDB 10.0 or higher

## Installation Methods

### Method 1: Upload via WordPress Dashboard

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Click "Choose File" and select the `seo-forge.zip` file
4. Click "Install Now"
5. After installation is complete, click "Activate Plugin"

### Method 2: Manual Upload via FTP

1. Extract the `seo-forge.zip` file to your local computer
2. Using an FTP client, connect to your web server
3. Upload the extracted `seo-forge` folder to the `/wp-content/plugins/` directory on your server
4. Log in to your WordPress admin dashboard
5. Navigate to **Plugins → Installed Plugins**
6. Find "SEO-Forge" in the list and click "Activate"

## Initial Configuration

After activating the plugin, you'll need to configure the API settings:

1. Navigate to **SEO-Forge → API Settings** in your WordPress admin menu
2. Enter your API Key (obtain from [SEO-Forge Portal](https://seo-forge.bitebase.app))
3. Verify the Content API and Image API endpoint URLs
4. Set your preferred default language
5. Configure auto-publish settings (optional)
6. Click "Save Settings"

## Plugin Features

1. **Blog Generator**: Create blog content from keywords
2. **Draft Manager**: Manage generated content before publishing
3. **Auto Scheduler**: Schedule automatic content generation and publishing
4. **API Settings**: Configure your SEO-Forge API connection

## Troubleshooting

If you encounter any issues during installation:

1. Verify your PHP version meets the minimum requirements (7.4+)
2. Check your WordPress version is 5.0 or higher
3. Ensure proper file permissions (755 for directories and 644 for files)
4. Verify the plugin directory is properly uploaded to `/wp-content/plugins/seo-forge/` (not nested like `/wp-content/plugins/seo-forge/seo-forge/`)
5. Confirm the main plugin file is at `/wp-content/plugins/seo-forge/seo-forge.php`
6. Check your server error logs for any PHP errors or warnings

### Common Installation Errors

- **Plugin file does not exist**: This usually means the ZIP file structure is incorrect. The plugin files should be inside a single `seo-forge` directory, not nested in multiple directories.
- **500 Internal Server Error during activation**: Check your server's PHP error logs and ensure all required plugin files are present.

## Support

For additional help, please visit [SEO-Forge Support](https://seo-forge.bitebase.app/support) or contact our technical support team at support@seo-forge.bitebase.app 