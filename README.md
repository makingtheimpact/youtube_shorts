# YouTube Shorts Slider - Production Ready

A secure, high-performance WordPress plugin for displaying YouTube Shorts in a responsive slider with advanced caching and admin tools.

## 🚀 Features

- **Responsive Design**: Mobile-first responsive slider that works on all devices
- **Multiple Play Modes**: Inline playback, popup modal, or redirect to YouTube
- **Smart Caching**: Configurable cache duration with automatic cleanup
- **Admin Tools**: Easy playlist management and cache purging
- **Security Focused**: Input validation, sanitization, and XSS protection
- **Performance Optimized**: Efficient API calls and minimal resource usage
- **Plugin Compatible**: Works with popular security and caching plugins

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **YouTube Data API v3**: API key required

## 🔧 Installation

1. Upload the plugin files to `/wp-content/plugins/youtube-shorts-slider/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Tools > YouTube Shorts Slider to configure your API key
4. Use the shortcode `[shorts_slider playlist="YOUR_PLAYLIST_ID"]` in your posts/pages

## ⚙️ Configuration

### API Key Setup

1. Visit [Google Cloud Console](https://console.developers.google.com/)
2. Create a new project or select existing one
3. Enable YouTube Data API v3
4. Create credentials (API Key)
5. Copy the API key to the plugin settings

### Default Settings

Configure default values for all shortcode parameters:
- Maximum videos per playlist
- Play mode (inline/popup/redirect)
- Cache duration
- Slider dimensions and layout
- Responsive column settings

## 📱 Shortcode Usage

### Basic Usage
```
[shorts_slider playlist="PLxxxxxxxx"]
```

### Advanced Usage
```
[shorts_slider 
    playlist="PLxxxxxxxx" 
    max="12" 
    play="popup" 
    cache_ttl="3600" 
    max_width="1200" 
    cols_desktop="4" 
    cols_tablet="2" 
    cols_mobile="1" 
    gap="15"
    center_on_click="true"
    thumb_quality="maxres"
    border_radius="20"
    title_color="#0066cc"
    title_hover_color="#003366"
    controls_spacing="40"
]
```

### New Features

#### Thumbnail Quality Control
Choose from multiple YouTube thumbnail qualities:
- `default` (120x90) - Smallest, fastest loading
- `medium` (320x180) - Balanced quality/size (default)
- `high` (480x360) - Better quality
- `standard` (640x480) - High quality
- `maxres` (1280x720) - Maximum resolution, best quality

#### Custom Styling
- **Border Radius**: Control thumbnail corner roundness (0-50px)
- **Title Colors**: Customize title text color and hover effects
- **Control Spacing**: Adjust space between navigation controls

#### Responsive Control Positioning
- **Mobile**: Control arrows positioned at left/right edges, pagination dots centered
- **Tablet/Desktop**: Centered layout with customizable spacing between controls

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `playlist` | string | required | YouTube playlist ID |
| `api_key` | string | admin setting | Custom API key |
| `max` | number | 20 | Maximum videos to display |
| `play` | string | inline | Play mode: inline, popup, redirect |
| `cache_ttl` | number | 86400 | Cache duration in seconds |
| `max_width` | number | 1450 | Maximum slider width (px) |
| `thumb_height` | string/number | auto | Thumbnail height |
| `cols_desktop` | number | 6 | Desktop columns |
| `cols_tablet` | number | 3 | Tablet columns |
| `cols_mobile` | number | 2 | Mobile columns |
| `gap` | number | 20 | Gap between items (px) |
| `center_on_click` | boolean | true | Center video on click |
| `controls_spacing` | number | 56 | Space between controls (px) |
| `controls_spacing_tablet` | number | 56 | Tablet controls spacing (px) |
| `controls_spacing_mobile` | number | 56 | Mobile controls spacing (px) |
| `controls_bottom_spacing` | number | 20 | Space below slider (px) |

## 🛡️ Security Features

- **Input Validation**: All user inputs are validated and sanitized
- **XSS Protection**: Output is properly escaped using WordPress functions
- **SQL Injection Prevention**: Prepared statements for database queries
- **Nonce Verification**: CSRF protection for admin actions
- **Capability Checks**: Admin functions require proper permissions
- **API Key Validation**: YouTube API key format validation

## 🔒 Compatibility

### Security Plugins
- **Wordfence**: Fully compatible, won't trigger false positives
- **Sucuri**: No conflicts with security scanning
- **iThemes Security**: Compatible with all security features
- **All In One WP Security**: No interference with security measures

### Caching Plugins
- **WP Rocket**: Compatible with page caching
- **W3 Total Cache**: Works with object and database caching
- **WP Super Cache**: No conflicts with page caching
- **LiteSpeed Cache**: Fully compatible

### Hosting Platforms
- **Shared Hosting**: Optimized for shared hosting environments
- **VPS/Dedicated**: Scales well with dedicated resources
- **Cloud Hosting**: Works with AWS, Google Cloud, Azure
- **Managed WordPress**: Compatible with WP Engine, Kinsta, etc.

## 📊 Performance

- **Efficient Caching**: Smart transient management with automatic cleanup
- **Minimal Database Queries**: Optimized database operations
- **Resource Loading**: CSS/JS only loaded when shortcode is used
- **API Rate Limiting**: Respects YouTube API quotas
- **Memory Efficient**: Minimal memory footprint

## 🚨 Error Handling

- **Graceful Degradation**: Plugin continues working even if API fails
- **User-Friendly Messages**: Clear error messages for administrators
- **Debug Logging**: Comprehensive logging when WP_DEBUG is enabled
- **Fallback Content**: Alternative content when videos can't be loaded

## 🧹 Maintenance

### Cache Management
- Automatic cache expiration
- Manual cache purging from admin
- Cleanup on plugin deactivation
- No orphaned database entries

### Database Cleanup
- Removes all plugin data on uninstall
- Cleans up transients and options
- No database bloat

## 🔧 Troubleshooting

### Common Issues

1. **"API key required" error**
   - Verify API key is set in admin settings
   - Check API key format and validity
   - Ensure YouTube Data API v3 is enabled

2. **"No videos found" error**
   - Verify playlist ID is correct
   - Check playlist is public
   - Purge cache and try again

3. **Slider not displaying**
   - Check shortcode syntax
   - Verify playlist contains videos
   - Check browser console for JavaScript errors

### Debug Mode

Enable WordPress debug mode to see detailed error logs:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 1.0.1
- Enhanced security with input validation and sanitization
- Improved error handling and user feedback
- Better compatibility with security plugins
- Performance optimizations and caching improvements
- Production-ready code structure

### Version 1.0.0
- Initial release with basic functionality

## 🤝 Support

For support and feature requests:
- **Website**: [Making The Impact LLC](https://makingtheimpact.com)
- **Documentation**: See inline code comments
- **WordPress.org**: Plugin support forum

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🔮 Roadmap

- **Analytics Integration**: Track video engagement
- **Multiple Playlists**: Support for playlist collections
- **Advanced Styling**: More customization options
- **Performance Monitoring**: Built-in performance metrics
- **API Caching**: Enhanced API response caching

## ⚠️ Important Notes

- **API Quotas**: YouTube Data API has daily quotas
- **Cache Duration**: Longer cache reduces API calls but may show outdated content
- **Mobile Performance**: Optimized for mobile devices
- **Accessibility**: WCAG compliant slider controls

---

**Made with ❤️ by Making The Impact LLC**
