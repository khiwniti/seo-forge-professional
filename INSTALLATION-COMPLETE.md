# SEO Forge Professional - Installation Guide

## 🚀 Quick Installation

### Method 1: WordPress Admin Upload

1. **Download the Plugin**:
   - Download `seo-forge-professional-complete.tar.gz`
   - Extract the archive to get the plugin folder

2. **Upload via WordPress Admin**:
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin"
   - Choose the plugin ZIP file
   - Click "Install Now"
   - Click "Activate Plugin"

### Method 2: FTP/Manual Upload

1. **Extract Plugin**:
   ```bash
   tar -xzf seo-forge-professional-complete.tar.gz
   ```

2. **Upload to WordPress**:
   ```bash
   # Upload the entire folder to your WordPress plugins directory
   cp -r seo-forge-professional/ /path/to/wordpress/wp-content/plugins/
   ```

3. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "SEO Forge Professional Complete"
   - Click "Activate"

## ⚙️ Initial Setup

### 1. Access the Plugin
- Go to WordPress Admin
- Look for "SEO Forge" in the admin menu (with chart icon)
- Click to access the main plugin interface

### 2. Configure Basic Settings
Navigate to the **Settings** tab and configure:

#### General Settings
- ✅ **Enable SEO Meta Tags**: Turn on meta tag generation
- ✅ **Enable Schema Markup**: Enable structured data
- ✅ **Enable XML Sitemap**: Generate XML sitemap

#### Analytics Settings
- **Google Analytics ID**: Enter your GA tracking ID (e.g., GA-XXXXXXXXX-X)
- ✅ **Enable Internal Tracking**: Turn on built-in analytics

#### AI Content Generation (Optional)
- **OpenAI API Key**: Enter your OpenAI API key for AI content generation
- **AI Model**: Choose between GPT-3.5 Turbo or GPT-4

### 3. Test the Installation

#### Dashboard Tab
- Check that stats are displaying
- Verify quick actions are working

#### Content Generator Tab
- Try generating content with a simple topic
- Verify the content preview appears

#### Analytics Tab
- Check that the date range selector works
- Verify charts are displaying (may be empty initially)

#### Keywords Tab
- Try adding a test keyword
- Verify it appears in the keywords table

## 🔧 System Requirements

### Minimum Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher
- **Memory**: 256MB recommended

### Required PHP Extensions
- `json` - For JSON handling
- `curl` - For API requests
- `mbstring` - For string handling

### Optional Requirements
- **OpenAI API Key** - For AI content generation
- **Google Analytics Account** - For enhanced analytics

## 🗄️ Database Setup

The plugin automatically creates the following database tables on activation:

1. **`wp_seo_forge_analytics`** - Stores page views and events
2. **`wp_seo_forge_keywords`** - Stores tracked keywords
3. **`wp_seo_forge_settings`** - Stores plugin settings

### Manual Database Check
If you need to verify the tables were created:
```sql
SHOW TABLES LIKE 'wp_seo_forge_%';
```

## 🎯 Post-Installation Tasks

### 1. Add SEO Meta to Existing Posts
- Edit any post or page
- Scroll down to find the "SEO Forge Settings" meta box
- Fill in meta title, description, and focus keyword
- Click "Analyze SEO" to get recommendations

### 2. Set Up Keyword Tracking
- Go to SEO Forge → Keywords tab
- Add important keywords you want to track
- Include target URLs for each keyword

### 3. Generate Your First Content
- Go to SEO Forge → Content Generator tab
- Enter a topic and keywords
- Select content length and type
- Click "Generate Content"

### 4. Monitor Analytics
- Go to SEO Forge → Analytics tab
- Set up date ranges to view data
- Check back regularly to monitor performance

## 🔍 Verification Checklist

After installation, verify these items:

- [ ] Plugin appears in WordPress admin menu
- [ ] All 5 tabs are accessible (Dashboard, Generator, Analytics, Keywords, Settings)
- [ ] SEO meta box appears on post/page edit screens
- [ ] Settings can be saved successfully
- [ ] No PHP errors in WordPress debug log
- [ ] Database tables were created
- [ ] Frontend tracking is working (if enabled)

## 🚨 Troubleshooting

### Plugin Won't Activate
**Error**: "Plugin requires PHP 8.0 or higher"
**Solution**: Upgrade your PHP version to 8.0 or higher

**Error**: "Plugin requires WordPress 6.0 or higher"
**Solution**: Update WordPress to version 6.0 or higher

### Missing Database Tables
**Issue**: Analytics or keywords not working
**Solution**: 
1. Deactivate the plugin
2. Reactivate the plugin (this will recreate tables)
3. Check WordPress debug log for any errors

### SEO Meta Box Not Showing
**Issue**: Meta box missing on post edit screen
**Solution**:
1. Go to post edit screen
2. Click "Screen Options" at the top
3. Ensure "SEO Forge Settings" is checked

### AI Content Generation Not Working
**Issue**: Content generation fails
**Solution**:
1. Check OpenAI API key in settings
2. Verify API key has proper permissions
3. Plugin will use fallback templates if API fails

### Analytics Not Tracking
**Issue**: No analytics data appearing
**Solution**:
1. Ensure "Enable Internal Tracking" is checked in settings
2. Check that JavaScript is loading on frontend
3. Wait 24 hours for data to accumulate

## 🔧 Advanced Configuration

### Custom Capabilities
The plugin creates custom capabilities:
- `manage_seo_forge` - Full plugin access
- `edit_seo_content` - Content editing
- `view_seo_analytics` - Analytics access

### Cron Jobs
The plugin sets up automated tasks:
- **Daily**: Analytics sync and cleanup
- **Weekly**: SEO reports (if enabled)

### REST API
Access the plugin via REST API:
- Base URL: `/wp-json/seo-forge/v1/`
- Authentication: WordPress nonce or application passwords

## 📞 Getting Help

### Built-in Help
- **Health Check**: Go to `/wp-json/seo-forge/v1/health` to check plugin status
- **Debug Info**: Enable WordPress debug mode for detailed error messages

### Common Solutions
1. **Clear Cache**: Clear any caching plugins after installation
2. **Check Permissions**: Ensure proper file permissions (644 for files, 755 for directories)
3. **Update WordPress**: Keep WordPress core updated
4. **Check Conflicts**: Temporarily deactivate other plugins to check for conflicts

---

## ✅ Installation Complete!

Once installed and configured, you'll have access to:
- 📊 **Comprehensive SEO Dashboard**
- 🤖 **AI-Powered Content Generation**
- 📈 **Advanced Analytics and Tracking**
- 🔍 **Keyword Ranking Monitoring**
- ⚙️ **Flexible Settings and Configuration**

All integrated into a single, memory-efficient interface using WordPress default UI components!