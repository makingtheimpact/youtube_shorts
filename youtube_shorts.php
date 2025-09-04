<?php
/**
 * Plugin Name: YouTube Shorts Slider
 * Plugin URI: https://makingtheimpact.com
 * Description: Display YouTube Shorts in a responsive slider with caching and admin tools. Production-ready with security features.
 * Version: 1.0.1
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Author: Making The Impact LLC
 * Author URI: https://makingtheimpact.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: youtube-shorts-slider
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access forbidden.');
}

// Define plugin constants
define('YTSS_VERSION', '1.0.1');
define('YTSS_PLUGIN_FILE', __FILE__);
define('YTSS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YTSS_PLUGIN_URL', plugin_dir_url(__FILE__));

final class YouTube_Shorts_Slider {
    const NONCE_ACTION = 'ytst_purge_nonce_action';
    const NONCE_NAME   = 'ytst_purge_nonce';
    const TRANSIENT_PREFIX = 'yt_shorts_';
    const CACHE_DURATION = 3600; // 1 hour
    const MAX_VIDEOS_LIMIT = 50;
    const MIN_CACHE_TTL = 300; // 5 minutes
    const MAX_CACHE_TTL = 604800; // 7 days
    
    // Error logging
    private static $errors = [];
    
    public static function init() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', [__CLASS__, 'php_version_notice']);
            return;
        }
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            add_action('admin_notices', [__CLASS__, 'wp_version_notice']);
            return;
        }
        
        // Initialize plugin
        add_action('admin_menu', [__CLASS__, 'add_tools_page']);
        add_action('admin_init', [__CLASS__, 'handle_post']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
        add_shortcode('shorts_slider', [__CLASS__, 'render_shorts_slider']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'handle_settings_save']);
        
        // Add activation/deactivation hooks
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
        
        // Add error logging
        add_action('admin_notices', [__CLASS__, 'display_errors']);
    }
    
    /**
     * Plugin activation hook
     */
    public static function activate() {
        // Set default options if they don't exist
        if (!get_option('youtube_shorts_defaults')) {
            $defaults = [
                'max' => 20,
                'play' => 'inline',
                'cache_ttl' => 86400,
                'max_width' => 1450,
                'thumb_height' => 'auto',
                'cols_desktop' => 6,
                'cols_tablet' => 3,
                'cols_mobile' => 2,
                'gap' => 20,
                'center_on_click' => true,
                'thumb_quality' => 'medium',
                'border_radius' => 16,
                'title_color' => '#111111',
                'title_hover_color' => '#000000',
                'controls_spacing' => 56,
                'controls_spacing_tablet' => 56,
                'controls_spacing_mobile' => 56,
                'controls_bottom_spacing' => 20,
                'arrow_border_radius' => 0,
                'arrow_padding' => 3,
                'arrow_width' => 35,
                'arrow_height' => 35,
                'arrow_bg_color' => '#111111',
                'arrow_hover_bg_color' => '#000000',
                'arrow_icon_color' => '#ffffff',
                'arrow_icon_size' => 28,
                'pagination_dot_color' => '#cfcfcf',
                'pagination_active_dot_color' => '#111111'
            ];
            update_option('youtube_shorts_defaults', $defaults);
        }
        
        // Clear any existing transients
        self::purge_transients_with_prefix(self::TRANSIENT_PREFIX);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Clear plugin transients
        self::purge_transients_with_prefix(self::TRANSIENT_PREFIX);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Display PHP version notice
     */
    public static function php_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>YouTube Shorts Slider:</strong> This plugin requires PHP 7.4 or higher. Your current version is ' . PHP_VERSION . '.';
        echo '</p></div>';
    }
    
    /**
     * Display WordPress version notice
     */
    public static function wp_version_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>YouTube Shorts Slider:</strong> This plugin requires WordPress 5.0 or higher. Your current version is ' . get_bloginfo('version') . '.';
        echo '</p></div>';
    }
    
    /**
     * Log errors for display
     */
    private static function log_error($message) {
        self::$errors[] = $message;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('YouTube Shorts Slider: ' . $message);
        }
    }
    
    /**
     * Display logged errors
     */
    public static function display_errors() {
        if (!empty(self::$errors) && current_user_can('manage_options')) {
            foreach (self::$errors as $error) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
            }
        }
    }

    public static function add_tools_page() {
        add_management_page(
            'YouTube Shorts Slider',       // page title
            'YouTube Shorts Slider',       // menu title (under Tools)
            'manage_options',      // capability
            'shorts-slider-tools', // slug
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings() {
        register_setting('youtube_shorts_api_options', 'youtube_api_key', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'sanitize_api_key'],
            'default' => ''
        ]);
        
        register_setting('youtube_shorts_defaults_options', 'youtube_shorts_defaults', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_defaults'],
            'default' => []
        ]);
    }
    
    /**
     * Sanitize API key input
     */
    public static function sanitize_api_key($input) {
        $input = sanitize_text_field($input);
        
        // Basic validation for YouTube API key format
        if (!empty($input) && !preg_match('/^[A-Za-z0-9_-]{39}$/', $input)) {
            add_settings_error(
                'youtube_api_key',
                'invalid_api_key',
                'Invalid YouTube API key format. Please check your key.',
                'error'
            );
            return '';
        }
        
        return $input;
    }
    
    /**
     * Sanitize hex color value
     */
    private static function sanitize_hex_color($color) {
        if (empty($color)) {
            return '#111111';
        }
        
        // Remove any non-hex characters
        $color = preg_replace('/[^0-9a-fA-F]/', '', $color);
        
        // Ensure it's a valid hex color
        if (strlen($color) === 6) {
            return '#' . $color;
        } elseif (strlen($color) === 3) {
            return '#' . $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
        }
        
        return '#111111';
    }

    /**
     * Sanitize and validate default settings
     */
    public static function sanitize_defaults($input) {
        if (!is_array($input)) {
            return [];
        }
        
        $sanitized = [];
        
        // Maximum videos
        $sanitized['max'] = isset($input['max']) ? 
            min(max(intval($input['max']), 1), self::MAX_VIDEOS_LIMIT) : 20;
        
        // Play mode
        $sanitized['play'] = isset($input['play']) && in_array($input['play'], ['inline', 'popup', 'redirect']) ? 
            $input['play'] : 'inline';
        
        // Cache TTL
        $sanitized['cache_ttl'] = isset($input['cache_ttl']) ? 
            min(max(intval($input['cache_ttl']), self::MIN_CACHE_TTL), self::MAX_CACHE_TTL) : 86400;
        
        // Max width
        $sanitized['max_width'] = isset($input['max_width']) ? 
            min(max(intval($input['max_width']), 200), 2000) : 1450;
        
        // Thumbnail height
        $sanitized['thumb_height'] = isset($input['thumb_height']) && in_array($input['thumb_height'], 
            ['auto', '80', '120', '160', '180', '200', '240', '300', '350', '400', '450', '500', '550', '600', '650']) ? 
            $input['thumb_height'] : 'auto';
        
        // Columns
        $sanitized['cols_desktop'] = isset($input['cols_desktop']) ? 
            min(max(intval($input['cols_desktop']), 1), 12) : 6;
        $sanitized['cols_tablet'] = isset($input['cols_tablet']) ? 
            min(max(intval($input['cols_tablet']), 1), 8) : 3;
        $sanitized['cols_mobile'] = isset($input['cols_mobile']) ? 
            min(max(intval($input['cols_mobile']), 1), 4) : 2;
        
        // Gap
        $sanitized['gap'] = isset($input['gap']) ? 
            min(max(intval($input['gap']), 0), 100) : 20;
        
        // Center on click
        $sanitized['center_on_click'] = isset($input['center_on_click']) ? 
            (bool) $input['center_on_click'] : true;
        
        // Thumbnail quality
        $sanitized['thumb_quality'] = isset($input['thumb_quality']) && in_array($input['thumb_quality'], 
            ['default', 'medium', 'high', 'standard', 'maxres']) ? 
            $input['thumb_quality'] : 'medium';
        
        // Style controls
        $sanitized['border_radius'] = isset($input['border_radius']) ? 
            min(max(intval($input['border_radius']), 0), 50) : 16;
        
        $sanitized['title_color'] = isset($input['title_color']) ? 
            self::sanitize_hex_color($input['title_color']) : '#111111';
        
        $sanitized['title_hover_color'] = isset($input['title_hover_color']) ? 
            self::sanitize_hex_color($input['title_hover_color']) : '#000000';
        
        $sanitized['controls_spacing'] = isset($input['controls_spacing']) ? 
            min(max(intval($input['controls_spacing']), 20), 200) : 56;
        
                $sanitized['controls_spacing_tablet'] = isset($input['controls_spacing_tablet']) ?
            min(max(intval($input['controls_spacing_tablet']), 20), 200) : 56;
        
        $sanitized['controls_spacing_mobile'] = isset($input['controls_spacing_mobile']) ?
            min(max(intval($input['controls_spacing_mobile']), 20), 200) : 56;
        
        $sanitized['controls_bottom_spacing'] = isset($input['controls_bottom_spacing']) ?
            min(max(intval($input['controls_bottom_spacing']), 10), 100) : 20;
        
        // Arrow styling settings
        $sanitized['arrow_border_radius'] = isset($input['arrow_border_radius']) ? 
            min(max(intval($input['arrow_border_radius']), 0), 50) : 0;
        
        $sanitized['arrow_padding'] = isset($input['arrow_padding']) ? 
            min(max(intval($input['arrow_padding']), 0), 20) : 3;
        
        $sanitized['arrow_width'] = isset($input['arrow_width']) ? 
            min(max(intval($input['arrow_width']), 20), 100) : 35;
        
        $sanitized['arrow_height'] = isset($input['arrow_height']) ? 
            min(max(intval($input['arrow_height']), 20), 100) : 35;
        
        $sanitized['arrow_bg_color'] = isset($input['arrow_bg_color']) ? 
            self::sanitize_hex_color($input['arrow_bg_color']) : '#111111';
        
        $sanitized['arrow_hover_bg_color'] = isset($input['arrow_hover_bg_color']) ? 
            self::sanitize_hex_color($input['arrow_hover_bg_color']) : '#000000';
        
        $sanitized['arrow_icon_color'] = isset($input['arrow_icon_color']) ? 
            self::sanitize_hex_color($input['arrow_icon_color']) : '#ffffff';
        
        $sanitized['arrow_icon_size'] = isset($input['arrow_icon_size']) ? 
            min(max(intval($input['arrow_icon_size']), 12), 48) : 28;
        
        // Pagination dot settings
        $sanitized['pagination_dot_color'] = isset($input['pagination_dot_color']) ? 
            self::sanitize_hex_color($input['pagination_dot_color']) : '#cfcfcf';
        
        $sanitized['pagination_active_dot_color'] = isset($input['pagination_active_dot_color']) ? 
            self::sanitize_hex_color($input['pagination_active_dot_color']) : '#111111';
        
        return $sanitized;
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Forbidden', 'Forbidden', ['response' => 403]);
        }

        // Admin notice after purge
        if (!empty($_GET['ytst_notice']) && $_GET['ytst_notice'] === 'purged') {
            echo '<div class="notice notice-success is-dismissible"><p>YouTube Shorts cache purged.</p></div>';
        }

        // Admin notice after API key save
        if (!empty($_GET['ytst_notice']) && $_GET['ytst_notice'] === 'api_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>YouTube API key saved successfully.</p></div>';
        }

        // Admin notice after default settings save
        if (!empty($_GET['ytst_notice']) && $_GET['ytst_notice'] === 'defaults_saved') {
            echo '<div class="notice notice-success is-dismissible"><p>Default shortcode settings saved successfully.</p></div>';
        }

        ?>
        <div class="wrap">
            <h1>YouTube Shorts Slider</h1>
            
            <?php
            // Display success message if settings were just saved
            if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
                $referer = wp_get_referer();
                if ($referer) {
                    if (strpos($referer, 'youtube_shorts_api_options') !== false) {
                        echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>✅ Success!</strong> YouTube API key saved successfully.</p></div>';
                    } elseif (strpos($referer, 'youtube_shorts_defaults_options') !== false) {
                        echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>✅ Success!</strong> Default shortcode settings saved successfully.</p></div>';
                    } else {
                        echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>✅ Success!</strong> Settings saved successfully.</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0;"><p><strong>✅ Success!</strong> Settings saved successfully.</p></div>';
                }
            }
            ?>
            
            <script>
            jQuery(document).ready(function($) {
                // Initialize color pickers
                $('.color-picker').wpColorPicker();
                
                // Auto-hide success messages after 5 seconds
                $('.notice-success').delay(5000).fadeOut(500);
            });
            </script>
            
            <!-- API Configuration Section -->
            <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h2 style="margin: 0 0 20px 0; color: #0073aa; font-size: 24px; border-bottom: 3px solid #0073aa; padding-bottom: 15px;">
                    🔑 API Configuration
                </h2>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px; line-height: 1.5;">
                    Configure your YouTube Data API v3 key to enable the plugin to fetch playlist data from YouTube.
                </p>
                <form method="post" action="options.php">
                    <?php settings_fields('youtube_shorts_api_options'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="youtube_api_key">YouTube API Key</label>
                            </th>
                            <td>
                                <input type="text" id="youtube_api_key" name="youtube_api_key" 
                                       value="<?php echo esc_attr(get_option('youtube_api_key', '')); ?>" 
                                       class="regular-text" required style="border: 2px solid #ddd; border-radius: 4px; padding: 8px;">
                                <p class="description">
                                    Get your API key from the <a href="https://console.developers.google.com/" target="_blank">Google Cloud Console</a>. 
                                    Enable the YouTube Data API v3 and create credentials.
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('💾 Save API Key', 'primary', 'submit', false, ['style' => 'background: #0073aa; border-color: #0073aa; padding: 8px 20px; font-size: 14px;']); ?>
                </form>
            </div>

            <!-- Default Shortcode Settings Section -->
            <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h2 style="margin: 0 0 20px 0; color: #0073aa; font-size: 24px; border-bottom: 3px solid #0073aa; padding-bottom: 15px;">
                    ⚙️ Default Shortcode Settings
                </h2>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px; line-height: 1.5;">
                    Configure default values for all shortcode parameters. These settings will be used when no specific values are provided in the shortcode.
                </p>

            <h2>Default Shortcode Settings</h2>
            <form method="post" action="options.php">
                <?php settings_fields('youtube_shorts_defaults_options'); ?>
                <?php $defaults = get_option('youtube_shorts_defaults', []); ?>
                <table class="form-table">
                    
                    <!-- Basic Configuration -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">📋 Basic Configuration</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max">Maximum Videos</label>
                        </th>
                        <td>
                            <input type="number" id="max" name="youtube_shorts_defaults[max]" 
                                   value="<?php echo esc_attr($defaults['max'] ?? 18); ?>" 
                                   class="small-text" min="1" max="50">
                            <p class="description">Maximum number of videos to load from playlist (default: 20)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="play">Play Mode</label>
                        </th>
                        <td>
                            <select id="play" name="youtube_shorts_defaults[play]">
                                <option value="inline" <?php selected($defaults['play'] ?? 'inline', 'inline'); ?>>Inline (embed in page)</option>
                                <option value="popup" <?php selected($defaults['play'] ?? 'inline', 'popup'); ?>>Popup/Modal</option>
                                <option value="redirect" <?php selected($defaults['play'] ?? 'inline', 'redirect'); ?>>Redirect to YouTube</option>
                            </select>
                            <p class="description">How videos should be played when clicked</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cache_ttl">Cache Duration (seconds)</label>
                        </th>
                        <td>
                            <input type="number" id="cache_ttl" name="youtube_shorts_defaults[cache_ttl]" 
                                   value="<?php echo esc_attr($defaults['cache_ttl'] ?? 86400); ?>" 
                                   class="small-text" min="300" max="604800">
                            <p class="description">How long to cache playlist data (default: 86400 = 24 hours)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="center_on_click">Center Video on Click</label>
                        </th>
                        <td>
                            <input type="hidden" name="youtube_shorts_defaults[center_on_click]" value="0">
                            <input type="checkbox" id="center_on_click" name="youtube_shorts_defaults[center_on_click]" 
                                   value="1" <?php checked($defaults['center_on_click'] ?? true, true); ?>>
                            <p class="description">Center the video in the slider when clicked (default: enabled)</p>
                        </td>
                    </tr>
                    
                    <!-- Layout & Grid Settings -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">🎯 Layout & Grid Settings</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="max_width">Maximum Width (px)</label>
                        </th>
                        <td>
                            <input type="number" id="max_width" name="youtube_shorts_defaults[max_width]" 
                                   value="<?php echo esc_attr($defaults['max_width'] ?? 1450); ?>" 
                                   class="small-text" min="200" max="2000">
                            <p class="description">Maximum width of the slider container (default: 1450px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cols_desktop">Desktop Columns</label>
                        </th>
                        <td>
                            <input type="number" id="cols_desktop" name="youtube_shorts_defaults[cols_desktop]" 
                                   value="<?php echo esc_attr($defaults['cols_desktop'] ?? 4); ?>" 
                                   class="small-text" min="1" max="12">
                            <p class="description">Number of videos visible per row on desktop screens (default: 6)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cols_tablet">Tablet Columns</label>
                        </th>
                        <td>
                            <input type="number" id="cols_tablet" name="youtube_shorts_defaults[cols_tablet]" 
                                   value="<?php echo esc_attr($defaults['cols_tablet'] ?? 3); ?>" 
                                   class="small-text" min="1" max="8">
                            <p class="description">Number of videos visible per row on tablet screens (default: 3)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="cols_mobile">Mobile Columns</label>
                        </th>
                        <td>
                            <input type="number" id="cols_mobile" name="youtube_shorts_defaults[cols_mobile]" 
                                   value="<?php echo esc_attr($defaults['cols_mobile'] ?? 2); ?>" 
                                   class="small-text" min="1" max="4">
                            <p class="description">Number of videos visible per row on mobile screens (default: 2)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="gap">Gap Between Items (px)</label>
                        </th>
                        <td>
                            <input type="number" id="gap" name="youtube_shorts_defaults[gap]" 
                                   value="<?php echo esc_attr($defaults['gap'] ?? 16); ?>" 
                                   class="small-text" min="0" max="100">
                            <p class="description">Space between video items (default: 16px)</p>
                        </td>
                    </tr>
                    
                    <!-- Thumbnail Settings -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">🖼️ Thumbnail Settings</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="thumb_height">Thumbnail Height</label>
                        </th>
                        <td>
                            <select id="thumb_height" name="youtube_shorts_defaults[thumb_height]">
                                <option value="auto" <?php selected($defaults['thumb_height'] ?? 'auto', 'auto'); ?>>Auto (proportional)</option>
                                <option value="80" <?php selected($defaults['thumb_height'] ?? 'auto', '80'); ?>>80px</option>
                                <option value="120" <?php selected($defaults['thumb_height'] ?? 'auto', '120'); ?>>120px</option>
                                <option value="160" <?php selected($defaults['thumb_height'] ?? 'auto', '160'); ?>>160px</option>
                                <option value="180" <?php selected($defaults['thumb_height'] ?? 'auto', '180'); ?>>180px</option>
                                <option value="200" <?php selected($defaults['thumb_height'] ?? 'auto', '200'); ?>>200px</option>
                                <option value="240" <?php selected($defaults['thumb_height'] ?? 'auto', '240'); ?>>240px</option>
                                <option value="300" <?php selected($defaults['thumb_height'] ?? 'auto', '300'); ?>>300px</option>
                                <option value="350" <?php selected($defaults['thumb_height'] ?? 'auto', '350'); ?>>350px</option>
                                <option value="400" <?php selected($defaults['thumb_height'] ?? 'auto', '400'); ?>>400px</option>
                                <option value="450" <?php selected($defaults['thumb_height'] ?? 'auto', '450'); ?>>450px</option>
                                <option value="500" <?php selected($defaults['thumb_height'] ?? 'auto', '500'); ?>>500px</option>
                                <option value="550" <?php selected($defaults['thumb_height'] ?? 'auto', '550'); ?>>550px</option>
                                <option value="600" <?php selected($defaults['thumb_height'] ?? 'auto', '600'); ?>>600px</option>
                                <option value="650" <?php selected($defaults['thumb_height'] ?? 'auto', '650'); ?>>650px</option>
                            </select>
                            <p class="description">Height of video thumbnails (default: auto)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="thumb_quality">Thumbnail Quality</label>
                        </th>
                        <td>
                            <select id="thumb_quality" name="youtube_shorts_defaults[thumb_quality]">
                                <option value="default" <?php selected($defaults['thumb_quality'] ?? 'medium', 'default'); ?>>Default (120x90)</option>
                                <option value="medium" <?php selected($defaults['thumb_quality'] ?? 'medium', 'medium'); ?>>Medium (320x180)</option>
                                <option value="high" <?php selected($defaults['thumb_quality'] ?? 'medium', 'high'); ?>>High (480x360)</option>
                                <option value="standard" <?php selected($defaults['thumb_quality'] ?? 'medium', 'standard'); ?>>Standard (640x480)</option>
                                <option value="maxres" <?php selected($defaults['thumb_quality'] ?? 'medium', 'maxres'); ?>>Max Resolution (1280x720)</option>
                            </select>
                            <p class="description">Quality of video thumbnails (default: medium)</p>
                        </td>
                    </tr>
                    
                    <!-- Visual Styling -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">🎨 Visual Styling</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="border_radius">Thumbnail Border Radius (px)</label>
                        </th>
                        <td>
                            <input type="number" id="border_radius" name="youtube_shorts_defaults[border_radius]" 
                                   value="<?php echo esc_attr($defaults['border_radius'] ?? 16); ?>" 
                                   class="small-text" min="0" max="50">
                            <p class="description">Border radius for thumbnail images (default: 16px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="title_color">Title Color</label>
                        </th>
                        <td>
                            <input type="text" id="title_color" name="youtube_shorts_defaults[title_color]" 
                                   value="<?php echo esc_attr($defaults['title_color'] ?? '#111111'); ?>" 
                                   class="color-picker" data-default-color="#111111">
                            <p class="description">Color of video titles (default: #111111)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="title_hover_color">Title Hover Color</label>
                        </th>
                        <td>
                            <input type="text" id="title_hover_color" name="youtube_shorts_defaults[title_hover_color]" 
                                   value="<?php echo esc_attr($defaults['title_hover_color'] ?? '#000000'); ?>" 
                                   class="color-picker" data-default-color="#000000">
                            <p class="description">Color of video titles on hover (default: #000000)</p>
                        </td>
                    </tr>
                    
                    <!-- Control Spacing -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">📏 Control Spacing</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="controls_spacing">Desktop Controls Spacing (px)</label>
                        </th>
                        <td>
                            <input type="number" id="controls_spacing" name="youtube_shorts_defaults[controls_spacing]" 
                                   value="<?php echo esc_attr($defaults['controls_spacing'] ?? 56); ?>" 
                                   class="small-text" min="20" max="200">
                            <p class="description">Space between slider controls on desktop screens (default: 56px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="controls_spacing_tablet">Tablet Controls Spacing (px)</label>
                        </th>
                        <td>
                            <input type="number" id="controls_spacing_tablet" name="youtube_shorts_defaults[controls_spacing_tablet]" 
                                   value="<?php echo esc_attr($defaults['controls_spacing_tablet'] ?? 56); ?>" 
                                   class="small-text" min="20" max="200">
                            <p class="description">Space between slider controls on tablet screens (default: 56px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="controls_spacing_mobile">Mobile Controls Spacing (px)</label>
                        </th>
                        <td>
                            <input type="number" id="controls_spacing_mobile" name="youtube_shorts_defaults[controls_spacing_mobile]" 
                                   value="<?php echo esc_attr($defaults['controls_spacing_mobile'] ?? 56); ?>" 
                                   class="small-text" min="20" max="200">
                            <p class="description">Space between slider controls on mobile screens (default: 56px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="controls_bottom_spacing">Controls Bottom Spacing (px)</label>
                        </th>
                        <td>
                            <input type="number" id="controls_bottom_spacing" name="youtube_shorts_defaults[controls_bottom_spacing]" 
                                   value="<?php echo esc_attr($defaults['controls_bottom_spacing'] ?? 20); ?>" 
                                   class="small-text" min="10" max="100">
                            <p class="description">Space between bottom of slider and controls (default: 20px)</p>
                        </td>
                    </tr>
                    
                    <!-- Arrow Button Styling -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">⬅️➡️ Arrow Button Styling</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_border_radius">Arrow Border Radius (px)</label>
                        </th>
                        <td>
                            <input type="number" id="arrow_border_radius" name="youtube_shorts_defaults[arrow_border_radius]" 
                                   value="<?php echo esc_attr($defaults['arrow_border_radius'] ?? 0); ?>" 
                                   class="small-text" min="0" max="50">
                            <p class="description">Border radius for arrow buttons (default: 0px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_padding">Arrow Padding (px)</label>
                        </th>
                        <td>
                            <input type="number" id="arrow_padding" name="youtube_shorts_defaults[arrow_padding]" 
                                   value="<?php echo esc_attr($defaults['arrow_padding'] ?? 3); ?>" 
                                   class="small-text" min="0" max="20">
                            <p class="description">Internal padding for arrow buttons (default: 3px)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_width">Arrow Width (px)</label>
                        </th>
                        <td>
                            <input type="number" id="arrow_width" name="youtube_shorts_defaults[arrow_width]" 
                                   value="<?php echo esc_attr($defaults['arrow_width'] ?? 35); ?>" 
                                   class="small-text" min="20" max="100">
                            <p class="description">Width of arrow buttons (default: 35px). The arrow icon will be automatically centered within this width.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_height">Arrow Height (px)</label>
                        </th>
                        <td>
                            <input type="number" id="arrow_height" name="youtube_shorts_defaults[arrow_height]" 
                                   value="<?php echo esc_attr($defaults['arrow_height'] ?? 35); ?>" 
                                   class="small-text" min="20" max="100">
                            <p class="description">Height of arrow buttons (default: 35px). The arrow icon will be automatically centered within this height.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_bg_color">Arrow Background Color</label>
                        </th>
                        <td>
                            <input type="text" id="arrow_bg_color" name="youtube_shorts_defaults[arrow_bg_color]" 
                                   value="<?php echo esc_attr($defaults['arrow_bg_color'] ?? '#111111'); ?>" 
                                   class="color-picker" data-default-color="#111111">
                            <p class="description">Background color of arrow buttons (default: #111111)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_hover_bg_color">Arrow Hover Background Color</label>
                        </th>
                        <td>
                            <input type="text" id="arrow_hover_bg_color" name="youtube_shorts_defaults[arrow_hover_bg_color]" 
                                   value="<?php echo esc_attr($defaults['arrow_hover_bg_color'] ?? '#000000'); ?>" 
                                   class="color-picker" data-default-color="#000000">
                            <p class="description">Background color of arrow buttons on hover (default: #000000)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_icon_color">Arrow Icon Color</label>
                        </th>
                        <td>
                            <input type="text" id="arrow_icon_color" name="youtube_shorts_defaults[arrow_icon_color]" 
                                   value="<?php echo esc_attr($defaults['arrow_icon_color'] ?? '#ffffff'); ?>" 
                                   class="color-picker" data-default-color="#ffffff">
                            <p class="description">Color of the arrow icons (default: #ffffff)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="arrow_icon_size">Arrow Icon Size (px)</label>
                        </th>
                        <td>
                            <input type="number" id="arrow_icon_size" name="youtube_shorts_defaults[arrow_icon_size]" 
                                   value="<?php echo esc_attr($defaults['arrow_icon_size'] ?? 28); ?>" 
                                   class="small-text" min="12" max="48">
                            <p class="description">Size of the arrow icons (default: 28px)</p>
                        </td>
                    </tr>
                    
                    <!-- Pagination Dot Styling -->
                    <tr>
                        <th scope="row" colspan="2">
                            <h3 style="margin: 0; padding: 15px 0; border-bottom: 2px solid #0073aa; color: #0073aa; font-size: 16px;">🔘 Pagination Dot Styling</h3>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pagination_dot_color">Pagination Dot Color</label>
                        </th>
                        <td>
                            <input type="text" id="pagination_dot_color" name="youtube_shorts_defaults[pagination_dot_color]" 
                                   value="<?php echo esc_attr($defaults['pagination_dot_color'] ?? '#cfcfcf'); ?>" 
                                   class="color-picker" data-default-color="#cfcfcf">
                            <p class="description">Color of inactive pagination dots (default: #cfcfcf)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="pagination_active_dot_color">Active Pagination Dot Color</label>
                        </th>
                        <td>
                            <input type="text" id="pagination_active_dot_color" name="youtube_shorts_defaults[pagination_active_dot_color]" 
                                   value="<?php echo esc_attr($defaults['pagination_active_dot_color'] ?? '#111111'); ?>" 
                                   class="color-picker" data-default-color="#111111">
                            <p class="description">Color of active pagination dot (default: #111111)</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('💾 Save Default Settings', 'primary', 'submit', false, ['style' => 'background: #0073aa; border-color: #0073aa; padding: 8px 20px; font-size: 14px;']); ?>
            </form>
            </div>

            <!-- Cache Management Section -->
            <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h2 style="margin: 0 0 20px 0; color: #0073aa; font-size: 24px; border-bottom: 3px solid #0073aa; padding-bottom: 15px;">
                    🗑️ Cache Management
                </h2>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px; line-height: 1.5;">
                    This clears all cached playlist data created by your <code>[shorts_slider]</code> shortcode (transients named <code><?php echo esc_html(self::TRANSIENT_PREFIX); ?>*</code>).
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                    <input type="hidden" name="ytst_action" value="purge">
                    <?php submit_button('🗑️ Purge Shorts Cache', 'primary', 'submit', false, ['style' => 'background: #dc3545; border-color: #dc3545; padding: 8px 20px; font-size: 14px;']); ?>
                </form>
            </div>

            <!-- Usage Section -->
            <div style="background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 8px; padding: 25px; margin: 30px 0;">
                <h2 style="margin: 0 0 20px 0; color: #0073aa; font-size: 24px; border-bottom: 3px solid #0073aa; padding-bottom: 15px;">
                    📚 Usage & Documentation
                </h2>
                <p style="margin: 0 0 20px 0; color: #666; font-size: 14px; line-height: 1.5;">
                    Use the shortcode <code>[shorts_slider playlist="YOUR_PLAYLIST_ID" max="20" play="inline" cache_ttl="86400" max_width="1450" thumb_height="auto" cols_desktop="6" cols_tablet="3" cols_mobile="2" gap="20" center_on_click="true" thumb_quality="maxres" border_radius="16" title_color="#111111" title_hover_color="#000000" controls_spacing="56" controls_spacing_tablet="56" controls_spacing_mobile="56" controls_bottom_spacing="20" arrow_border_radius="0" arrow_padding="3" arrow_width="35" arrow_height="35" arrow_bg_color="#111111" arrow_hover_bg_color="#000000" arrow_icon_color="#ffffff" arrow_icon_size="28" pagination_dot_color="#cfcfcf" pagination_active_dot_color="#111111"]</code> in your posts or pages.
                </p>
            
                <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px; margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0; color: #0073aa; font-size: 18px; border-bottom: 2px solid #e1e5e9; padding-bottom: 10px;">
                        📋 Parameters
                    </h3>
                    <ul style="margin: 0; padding-left: 20px; color: #333; line-height: 1.6;">
                        <li><strong>playlist</strong> (required): YouTube playlist ID from the playlist URL</li>
                        <li><strong>api_key</strong> (optional): Custom YouTube API key (overrides admin setting)</li>
                        <li><strong>max</strong> (optional): Maximum number of videos to display (default: 18)</li>
                        <li><strong>play</strong> (optional): How videos play - "inline", "popup", or "redirect" (default: inline)</li>
                        <li><strong>cache_ttl</strong> (optional): Cache duration in seconds (default: 86400 = 24 hours)</li>
                        <li><strong>max_width</strong> (optional): Maximum width of grid in pixels (default: 1450)</li>
                        <li><strong>thumb_height</strong> (optional): Thumbnail height - "auto", "80", "120", "160", "180", "200", "240", or "300" (default: auto)</li>
                        <li><strong>cols_desktop</strong> (optional): Number of videos visible per row on desktop (default: 6)</li>
                        <li><strong>cols_tablet</strong> (optional): Number of videos visible per row on tablet (default: 3)</li>
                        <li><strong>cols_mobile</strong> (optional): Number of videos visible per row on mobile (default: 2)</li>
                        <li><strong>gap</strong> (optional): Gap between video items in pixels (default: 20)</li>
                        <li><strong>center_on_click</strong> (optional): Whether to center the video when clicked - "true" or "false" (default: true)</li>
                        <li><strong>thumb_quality</strong> (optional): Thumbnail quality - "default", "medium", "high", "standard", or "maxres" (default: medium)</li>
                        <li><strong>border_radius</strong> (optional): Border radius for thumbnails in pixels (default: 16)</li>
                        <li><strong>title_color</strong> (optional): Color of video titles in hex format (default: #111111)</li>
                        <li><strong>title_hover_color</strong> (optional): Color of video titles on hover in hex format (default: #000000)</li>
                        <li><strong>controls_spacing</strong> (optional): Space between slider controls in pixels (default: 56)</li>
                        <li><strong>controls_spacing_tablet</strong> (optional): Space between slider controls on tablet screens in pixels (default: 56)</li>
                        <li><strong>controls_spacing_mobile</strong> (optional): Space between slider controls on mobile screens in pixels (default: 56)</li>
                        <li><strong>controls_bottom_spacing</strong> (optional): Space between bottom of slider and controls in pixels (default: 20)</li>
                        <li><strong>arrow_border_radius</strong> (optional): Border radius for arrow buttons in pixels (default: 0)</li>
                        <li><strong>arrow_padding</strong> (optional): Internal padding for arrow buttons in pixels (default: 3)</li>
                        <li><strong>arrow_width</strong> (optional): Width of arrow buttons in pixels (default: 35)</li>
                        <li><strong>arrow_height</strong> (optional): Height of arrow buttons in pixels (default: 35)</li>
                        <li><strong>arrow_bg_color</strong> (optional): Background color of arrow buttons in hex format (default: #111111)</li>
                        <li><strong>arrow_hover_bg_color</strong> (optional): Background color of arrow buttons on hover in hex format (default: #000000)</li>
                        <li><strong>arrow_icon_color</strong> (optional): Color of the arrow icons in hex format (default: #ffffff)</li>
                        <li><strong>arrow_icon_size</strong> (optional): Size of the arrow icons in pixels (default: 28)</li>
                        <li><strong>pagination_dot_color</strong> (optional): Color of inactive pagination dots in hex format (default: #cfcfcf)</li>
                        <li><strong>pagination_active_dot_color</strong> (optional): Color of active pagination dot in hex format (default: #111111)</li>
                    </ul>
                </div>

                <div style="background: #fff; border: 1px solid #e1e5e9; border-radius: 6px; padding: 20px; margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0; color: #0073aa; font-size: 18px; border-bottom: 2px solid #e1e5e9; padding-bottom: 10px;">
                        💡 Examples
                    </h3>
            <p><strong>Basic usage:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx"]</code></p>
            
            <p><strong>Custom slider layout:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" max="12" cols_desktop="4" cols_tablet="2" cols_mobile="1" gap="15"]</code></p>
            
            <p><strong>Popup mode with custom cache:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" play="popup" cache_ttl="3600" max_width="1200"]</code></p>
            
            <p><strong>Redirect to YouTube:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" play="redirect" thumb_height="180"]</code></p>
            
            <p><strong>Disable video centering:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" center_on_click="false"]</code></p>
            
            <p><strong>High quality thumbnails:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" thumb_quality="maxres"]</code></p>
            
            <p><strong>Custom styling:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" border_radius="20" title_color="#0066cc" title_hover_color="#003366"]</code></p>
            
            <p><strong>Compact controls:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" controls_spacing="30"]</code></p>
            
            <p><strong>Responsive control spacing:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" controls_spacing="80" controls_spacing_tablet="40" controls_spacing_mobile="30"]</code></p>
            
            <p><strong>Custom bottom spacing:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" controls_bottom_spacing="40"]</code></p>
            
            <p><strong>Custom arrow styling:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" arrow_border_radius="8" arrow_width="40" arrow_height="40" arrow_padding="5" arrow_bg_color="#0066cc" arrow_hover_bg_color="#004499" arrow_icon_color="#ffffff" arrow_icon_size="24"]</code></p>
            
            <p><strong>Custom pagination dots:</strong><br>
            <code>[shorts_slider playlist="PLxxxxxxxx" pagination_dot_color="#e0e0e0" pagination_active_dot_color="#0066cc"]</code></p>
            
            <p><strong>Backward compatibility:</strong><br>
            <code>[shorts_slider playlist_id="PLxxxxxxxx" max_videos="10"]</code> (still works)</p>
            
            <p><strong>Note:</strong> The slider displays videos in a single row with pagination controls. Use the arrow buttons or dots to navigate through all videos in the playlist.</p>
                </div>

                <!-- Troubleshooting Section -->
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 20px; margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0; color: #856404; font-size: 18px; border-bottom: 2px solid #ffeaa7; padding-bottom: 10px;">
                        🔧 Troubleshooting
                    </h3>
                    <p style="margin: 0; color: #856404; line-height: 1.6;">
                        <strong>If new videos don't appear:</strong> Purge the cache using the button above, then reload the page where the slider is embedded. The shortcode will re-fetch and re-cache from YouTube.
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    public static function handle_post() {
        if (
            !is_admin() ||
            !current_user_can('manage_options') ||
            empty($_POST['ytst_action']) ||
            $_POST['ytst_action'] !== 'purge'
        ) {
            return;
        }

        // Nonce check
        check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        self::purge_transients_with_prefix(self::TRANSIENT_PREFIX);

        // Redirect to avoid resubmits and show notice
        $url = add_query_arg('ytst_notice', 'purged', menu_page_url('shorts-slider-tools', false));
        wp_safe_redirect($url);
        exit;
    }

    /**
     * Handle settings form submissions and show success messages
     */
    public static function handle_settings_save() {
        // This function is no longer needed since we're handling success messages inline
        // WordPress automatically handles the settings-updated parameter
    }

    /**
     * Enqueue necessary scripts and styles for the slider
     */
    public static function enqueue_scripts() {
        // Check if any FontAwesome version is already loaded
        $fa_loaded = wp_style_is('font-awesome', 'enqueued') || 
                     wp_style_is('fontawesome', 'enqueued') || 
                     wp_style_is('font-awesome-5', 'enqueued') ||
                     wp_style_is('font-awesome-6', 'enqueued') ||
                     wp_style_is('font-awesome-4', 'enqueued');
        
        if (!$fa_loaded) {
            // Load FontAwesome 6.4.0 with a unique handle to avoid conflicts
            $fontawesome_url = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            wp_enqueue_style('ytss-font-awesome', $fontawesome_url, [], '6.4.0');
        }
        
        // Always add our custom CSS for consistent arrow display
        add_action('wp_head', function() use ($fa_loaded) {
            echo '<style>
            /* YouTube Shorts Slider - Arrow Controls (Plugin-specific selectors) */
            .ytrow-arrow {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            .ytrow-arrow span {
                position: relative;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
            }
            
            /* FontAwesome icons styling */
            .ytrow-arrow .fa-solid.fa-angle-left,
            .ytrow-arrow .fa-solid.fa-angle-right {
                font-size: inherit;
                line-height: 1;
                display: block;
                color: inherit;
            }
            
            /* CSS-only arrow fallbacks - ONLY show when FontAwesome fails */
            .ytrow-arrow.fa-fallback[data-dir="prev"]::before,
            .ytrow-arrow.fa-fallback[data-dir="next"]::before {
                content: "";
                position: absolute;
                width: 0;
                height: 0;
                border: solid transparent;
                border-width: 8px;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }
            
            .ytrow-arrow.fa-fallback[data-dir="prev"]::before {
                border-right-color: currentColor;
                margin-left: -2px;
            }
            
            .ytrow-arrow.fa-fallback[data-dir="next"]::before {
                border-left-color: currentColor;
                margin-left: 2px;
            }
            

            
            /* Ensure proper icon sizing and positioning */
            .ytrow-arrow i {
                font-size: inherit !important;
                line-height: 1 !important;
                display: block !important;
                width: auto !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            /* CSS-only arrow sizing based on container */
            .ytrow-arrow.fa-fallback[data-dir="prev"]::before,
            .ytrow-arrow.fa-fallback[data-dir="next"]::before {
                border-width: calc(var(--arrow-icon-size, 28px) * 0.3);
            }
            
            /* Ensure arrows are visible and properly sized */
            .ytrow-arrow .fa-solid.fa-angle-left,
            .ytrow-arrow .fa-solid.fa-angle-right {
                font-size: var(--arrow-icon-size, 28px) !important;
                color: var(--arrow-icon-color, #ffffff) !important;
            }
            
            /* Hide Font Awesome icons when using CSS fallback */
            .ytrow-arrow.fa-fallback .fa-solid.fa-angle-left,
            .ytrow-arrow.fa-fallback .fa-solid.fa-angle-right {
                display: none !important;
            }
            </style>';
        });
    }

    /**
     * Enqueue admin scripts and styles
     */
    public static function enqueue_admin_scripts($hook) {
        // Only load on our plugin page
        if ($hook !== 'tools_page_youtube-shorts-slider') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /**
     * Render the shorts slider shortcode
     */
    public static function render_shorts_slider($atts) {
        try {
            // Get defaults from admin settings
            $defaults = get_option('youtube_shorts_defaults', []);
            
            // Sanitize and validate attributes
            $atts = shortcode_atts([
                'playlist' => '',
                'playlist_id' => '', // Backward compatibility
                'api_key' => '',
                'max' => $defaults['max'] ?? 20,
                'play' => $defaults['play'] ?? 'inline',
                'cache_ttl' => $defaults['cache_ttl'] ?? 86400,
                'max_width' => $defaults['max_width'] ?? 1450,
                'thumb_height' => $defaults['thumb_height'] ?? 'auto',
                'cols_desktop' => $defaults['cols_desktop'] ?? 6,
                'cols_tablet' => $defaults['cols_tablet'] ?? 3,
                'cols_mobile' => $defaults['cols_mobile'] ?? 2,
                'gap' => $defaults['gap'] ?? 20,
                'center_on_click' => $defaults['center_on_click'] ?? true,
                'thumb_quality' => $defaults['thumb_quality'] ?? 'medium',
                'border_radius' => $defaults['border_radius'] ?? 16,
                'title_color' => $defaults['title_color'] ?? '#111111',
                'title_hover_color' => $defaults['title_hover_color'] ?? '#000000',
                'controls_spacing' => $defaults['controls_spacing'] ?? 56,
                'controls_spacing_tablet' => $defaults['controls_spacing_tablet'] ?? 56,
                'controls_spacing_mobile' => $defaults['controls_spacing_mobile'] ?? 56,
                'controls_bottom_spacing' => $defaults['controls_bottom_spacing'] ?? 20,
                'arrow_border_radius' => $defaults['arrow_border_radius'] ?? 0,
                'arrow_padding' => $defaults['arrow_padding'] ?? 3,
                'arrow_width' => $defaults['arrow_width'] ?? 35,
                'arrow_height' => $defaults['arrow_height'] ?? 35,
                'arrow_bg_color' => $defaults['arrow_bg_color'] ?? '#111111',
                'arrow_hover_bg_color' => $defaults['arrow_hover_bg_color'] ?? '#000000',
                'arrow_icon_color' => $defaults['arrow_icon_color'] ?? '#ffffff',
                'arrow_icon_size' => $defaults['arrow_icon_size'] ?? 28,
                'pagination_dot_color' => $defaults['pagination_dot_color'] ?? '#cfcfcf',
                'pagination_active_dot_color' => $defaults['pagination_active_dot_color'] ?? '#111111'
            ], $atts);

            // Validate and sanitize inputs
            $playlist_id = self::sanitize_playlist_id(
                !empty($atts['playlist']) ? $atts['playlist'] : $atts['playlist_id']
            );
            
            if (empty($playlist_id)) {
                return self::render_error('Playlist ID is required.');
            }

            // Validate max videos
            $max_videos = min(max(intval($atts['max']), 1), self::MAX_VIDEOS_LIMIT);
            
            // Validate play mode
            $play_mode = in_array($atts['play'], ['inline', 'popup', 'redirect']) ? $atts['play'] : 'inline';
            
            // Validate cache TTL
            $cache_ttl = min(max(intval($atts['cache_ttl']), self::MIN_CACHE_TTL), self::MAX_CACHE_TTL);
            
            // Validate dimensions
            $max_width = min(max(intval($atts['max_width']), 200), 2000);
            $gap = min(max(intval($atts['gap']), 0), 100);
            
            // Validate columns
            $cols_desktop = min(max(intval($atts['cols_desktop']), 1), 12);
            $cols_tablet = min(max(intval($atts['cols_tablet']), 1), 8);
            $cols_mobile = min(max(intval($atts['cols_mobile']), 1), 4);
            
            // Validate thumbnail height
            $thumb_height = ($atts['thumb_height'] === 'auto') ? 0 : intval($atts['thumb_height']);
            if ($thumb_height > 0 && !in_array($thumb_height, [80, 120, 160, 180, 200, 240, 300, 350, 400, 450, 500, 550, 600, 650])) {
                $thumb_height = 0; // Reset to auto if invalid
            }
            
            // Validate center on click
            $center_on_click = (bool) $atts['center_on_click'];

            // Validate thumbnail quality
            $thumb_quality = in_array($atts['thumb_quality'], ['default', 'medium', 'high', 'standard', 'maxres']) ? $atts['thumb_quality'] : 'medium';

            // Validate border radius
            $border_radius = min(max(intval($atts['border_radius']), 0), 50);

            // Validate title color
            $title_color = sanitize_hex_color($atts['title_color']) ?: '#111111';

            // Validate title hover color
            $title_hover_color = sanitize_hex_color($atts['title_hover_color']) ?: '#000000';

            // Validate controls spacing
            $controls_spacing = min(max(intval($atts['controls_spacing']), 20), 200);

            // Validate tablet controls spacing
            $controls_spacing_tablet = min(max(intval($atts['controls_spacing_tablet']), 20), 200);
            
            // Validate mobile controls spacing
            $controls_spacing_mobile = min(max(intval($atts['controls_spacing_mobile']), 20), 200);
            
            // Validate controls bottom spacing
            $controls_bottom_spacing = min(max(intval($atts['controls_bottom_spacing']), 10), 100);

            // Validate arrow styling settings
            $arrow_border_radius = min(max(intval($atts['arrow_border_radius']), 0), 50);
            $arrow_padding = min(max(intval($atts['arrow_padding']), 0), 20);
            $arrow_width = min(max(intval($atts['arrow_width']), 20), 100);
            $arrow_height = min(max(intval($atts['arrow_height']), 20), 100);
            $arrow_bg_color = sanitize_hex_color($atts['arrow_bg_color']) ?: '#111111';
            $arrow_hover_bg_color = sanitize_hex_color($atts['arrow_hover_bg_color']) ?: '#000000';
            $arrow_icon_color = sanitize_hex_color($atts['arrow_icon_color']) ?: '#ffffff';
            $arrow_icon_size = min(max(intval($atts['arrow_icon_size']), 12), 48);

            // Validate pagination dot settings
            $pagination_dot_color = sanitize_hex_color($atts['pagination_dot_color']) ?: '#cfcfcf';
            $pagination_active_dot_color = sanitize_hex_color($atts['pagination_active_dot_color']) ?: '#111111';

            // Use custom API key if provided, otherwise use admin setting
            $api_key = self::sanitize_api_key(
                !empty($atts['api_key']) ? $atts['api_key'] : get_option('youtube_api_key', '')
            );
            
            if (empty($api_key)) {
                return self::render_error('YouTube API key is required. Please configure it in the admin panel.');
            }

            $videos = self::get_playlist_videos($playlist_id, $max_videos, $cache_ttl, $api_key, $thumb_quality);
            
            if (empty($videos)) {
                return self::render_error('No videos found or error fetching playlist. Please check your playlist ID and API key.');
            }

            // Generate unique ID for this slider instance
            $uid = 'ytrow_' . str_replace('-', '_', wp_generate_uuid4());
            
            // Ensure all variables are defined and sanitized
            $gap = intval($atts['gap']);
            $border_radius = intval($border_radius);
            $title_color = sanitize_hex_color($title_color) ?: '#111111';
            $title_hover_color = sanitize_hex_color($title_hover_color) ?: '#000000';
            $thumb_height = intval($thumb_height);
            $controls_spacing = intval($controls_spacing);
            $controls_spacing_tablet = intval($controls_spacing_tablet);
            $controls_spacing_mobile = intval($controls_spacing_mobile);
            $controls_bottom_spacing = intval($controls_bottom_spacing);
            $arrow_border_radius = intval($arrow_border_radius);
            $arrow_padding = intval($arrow_padding);
            $arrow_width = intval($arrow_width);
            $arrow_height = intval($arrow_height);
            $arrow_bg_color = sanitize_hex_color($arrow_bg_color) ?: '#111111';
            $arrow_hover_bg_color = sanitize_hex_color($arrow_hover_bg_color) ?: '#000000';
            $arrow_icon_color = sanitize_hex_color($arrow_icon_color) ?: '#ffffff';
            $arrow_icon_size = intval($arrow_icon_size);
            $pagination_dot_color = sanitize_hex_color($pagination_dot_color) ?: '#cfcfcf';
            $pagination_active_dot_color = sanitize_hex_color($pagination_active_dot_color) ?: '#111111';
            $cols_desktop = intval($atts['cols_desktop']);
            $cols_tablet = intval($atts['cols_tablet']);
            $cols_mobile = intval($atts['cols_mobile']);
        
        ob_start();
        ?>
        <div class="ytrow-outer" style="max-width:<?php echo esc_attr($atts['max_width']); ?>px;margin:0 auto;width:100%;">
            <div class="ytrow-wrap" id="<?php echo esc_attr($uid); ?>"
                 data-play="<?php echo esc_attr($play_mode); ?>"
                 data-thumb-max="<?php echo esc_attr($thumb_height); ?>"
                 data-cols-desktop="<?php echo esc_attr($atts['cols_desktop']); ?>"
                 data-cols-tablet="<?php echo esc_attr($atts['cols_tablet']); ?>"
                 data-cols-mobile="<?php echo esc_attr($atts['cols_mobile']); ?>"
                 data-center-on-click="<?php echo esc_attr($atts['center_on_click'] ? 'true' : 'false'); ?>"
                 data-thumb-quality="<?php echo esc_attr($thumb_quality); ?>"
                 data-border-radius="<?php echo esc_attr($border_radius); ?>"
                 data-title-color="<?php echo esc_attr($title_color); ?>"
                 data-title-hover-color="<?php echo esc_attr($title_hover_color); ?>"
                 data-controls-spacing="<?php echo esc_attr($controls_spacing); ?>"
                 data-controls-spacing-tablet="<?php echo esc_attr($controls_spacing_tablet); ?>"
                 data-controls-spacing-mobile="<?php echo esc_attr($controls_spacing_mobile); ?>"
                 data-controls-bottom-spacing="<?php echo esc_attr($controls_bottom_spacing); ?>">

                <div class="ytrow-strip" role="list">
                    <?php foreach ($videos as $video): ?>
                        <button class="ytrow-card" role="listitem" data-id="<?php echo esc_attr($video['video_id']); ?>" title="<?php echo esc_attr($video['title']); ?>">
                            <span class="ytrow-thumb">
                                <img src="<?php echo esc_url($video['thumbnail']); ?>" alt="">
                            </span>
                            <span class="ytrow-meta"><?php echo esc_html($video['title']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="ytrow-controls">
                    <button class="ytrow-arrow" data-dir="prev" aria-label="Previous">
                        <span><i class="fa-solid fa-angle-left"></i></span>
                    </button>
                    <div class="ytrow-dots" aria-label="Slider pagination"></div>
                    <button class="ytrow-arrow" data-dir="next" aria-label="Next">
                        <span><i class="fa-solid fa-angle-right"></i></span>
                    </button>
                </div>

                <?php if (is_user_logged_in() && current_user_can('manage_options')): ?>
                    <div class="ytrow-admin">
                        <button type="button" class="ytrow-refresh" onclick="location.reload()">↻ Refresh Playlist</button>
                    </div>
                <?php endif; ?>

                <div class="ytrow-modal">
                    <div class="ytrow-modal-backdrop"></div>
                    <div class="ytrow-modal-inner" role="dialog" aria-modal="true" aria-label="Video player">
                        <button class="ytrow-close" aria-label="Close">×</button>
                        <div class="ytrow-player"></div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            /* Scope + variables */
            #<?php echo esc_attr($uid); ?>{
                --gap: <?php echo esc_attr($gap); ?>px;
                --radius: <?php echo esc_attr($border_radius); ?>px;
                --ink: <?php echo esc_attr($title_color); ?>;
                --ink-hover: <?php echo esc_attr($title_hover_color); ?>;
                --muted:#6a6a6a;

                --thumb-max: <?php echo esc_attr($thumb_height); ?>px;
                --controls-spacing: <?php echo esc_attr($controls_spacing); ?>px;
                --controls-spacing-tablet: <?php echo esc_attr($controls_spacing_tablet); ?>px;
                --controls-spacing-mobile: <?php echo esc_attr($controls_spacing_mobile); ?>px;
                --controls-bottom-spacing: <?php echo esc_attr($controls_bottom_spacing); ?>px;

                /* Arrow styling variables */
                --arrow-border-radius: <?php echo esc_attr($arrow_border_radius); ?>px;
                --arrow-padding: <?php echo esc_attr($arrow_padding); ?>px;
                --arrow-width: <?php echo esc_attr($arrow_width); ?>px;
                --arrow-height: <?php echo esc_attr($arrow_height); ?>px;
                --arrow-bg-color: <?php echo esc_attr($arrow_bg_color); ?>;
                --arrow-hover-bg-color: <?php echo esc_attr($arrow_hover_bg_color); ?>;
                --arrow-icon-color: <?php echo esc_attr($arrow_icon_color); ?>;
                --arrow-icon-size: <?php echo esc_attr($arrow_icon_size); ?>px;

                /* Pagination dot variables */
                --pagination-dot-color: <?php echo esc_attr($pagination_dot_color); ?>;
                --pagination-active-dot-color: <?php echo esc_attr($pagination_active_dot_color); ?>;

                /* default = mobile; tablet/desktop override below */
                --cols: <?php echo esc_attr($cols_mobile); ?>;

                /* card width from visible strip width and cols */
                --cardw: calc( (100% - (var(--gap) * (var(--cols) - 1))) / var(--cols) );

                position:relative;
            }
            @media (min-width:768px){  #<?php echo esc_attr($uid); ?>{ --cols: <?php echo esc_attr($cols_tablet); ?>; } }
            @media (min-width:1024px){ #<?php echo esc_attr($uid); ?>{ --cols: <?php echo esc_attr($cols_desktop); ?>; } }

            /* Scroller = grid with fixed column width per card */
            #<?php echo esc_attr($uid); ?> .ytrow-strip{
                display:grid;
                grid-auto-flow: column;
                grid-auto-columns: var(--cardw);
                gap:var(--gap);
                overflow-x:auto;
                scroll-snap-type:x mandatory;
                padding:0;
                width:100%;
                scrollbar-width:none;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-strip::-webkit-scrollbar{ display:none; }

            /* Card: width is from grid-auto-columns; no max/mins */
            #<?php echo esc_attr($uid); ?> .ytrow-card{
                display:flex; flex-direction:column;
                cursor:pointer; border:0; background:transparent; padding:0; text-align:left;
                scroll-snap-align:start;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-card:focus{ outline:1px solid #ccc; outline-offset:2px; }

            /* Thumb: 9:16 via aspect-ratio; optional max-height cap */
            #<?php echo esc_attr($uid); ?> .ytrow-thumb{
                position:relative; width:100%;
                aspect-ratio: 9 / 16;
                height:auto;
                <?php if ($thumb_height > 0): ?>
                max-height: <?php echo $thumb_height; ?>px;
                <?php endif; ?>
                background:#000; border-radius:var(--radius); overflow:hidden;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-thumb > *{
                position:absolute; inset:0; width:100%; height:100%;
                border:0 !important; display:block; object-fit:cover;
            }

            /* Title */
            #<?php echo esc_attr($uid); ?> .ytrow-meta{
                padding:10px 2px 0; margin:0; color:var(--ink);
                font-size:16px; font-weight:700; line-height:1.25;
                display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden;
                transition:color 0.2s ease;
            }
            
            /* Title hover effect */
            #<?php echo esc_attr($uid); ?> .ytrow-card:hover .ytrow-meta{
                color:var(--ink-hover);
            }

            /* Controls */
            #<?php echo esc_attr($uid); ?> .ytrow-controls{
                margin-top:var(--controls-bottom-spacing); display:flex; align-items:center; justify-content:space-between; gap:var(--controls-spacing);
                position:relative;
            }
            
            /* General arrow styles */
            #<?php echo esc_attr($uid); ?> .ytrow-arrow{
                width:var(--arrow-width); height:var(--arrow-height); border-radius:var(--arrow-border-radius); border:0;
                background:var(--arrow-bg-color); position:relative; cursor:pointer; flex:0 0 auto;
                transition:transform .12s ease, background-color .12s ease, box-shadow .12s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
            }
            
            /* Mobile: arrows on edges, dots in center */
            @media (max-width: 767px) {
                #<?php echo esc_attr($uid); ?> .ytrow-controls{
                    justify-content:space-between;
                    gap:var(--controls-spacing-mobile);
                    min-height: calc(var(--arrow-height) + 20px); /* Ensure consistent height for alignment with dynamic arrow size */
                    align-items: center; /* Ensure vertical centering */
                    position: relative; /* Container for absolute positioning */
                }
                #<?php echo esc_attr($uid); ?> .ytrow-arrow{
                    position:absolute !important; /* Override general styles */
                    top:50%;
                    transform:translateY(-50%);
                    z-index: 1; /* Ensure arrows are above dots */
                }
                #<?php echo esc_attr($uid); ?> .ytrow-arrow[data-dir="prev"]{
                    left:0;
                }
                #<?php echo esc_attr($uid); ?> .ytrow-arrow[data-dir="next"]{
                    right:0;
                }
                #<?php echo esc_attr($uid); ?> .ytrow-dots{
                    position:absolute !important; /* Override general styles */
                    left:50%;
                    top:50%;
                    transform:translate(-50%, -50%);
                    z-index: 2; /* Ensure dots are above arrows */
                    display: flex !important; /* Maintain flexbox for dot alignment */
                }
            }
            
            /* Tablet: centered layout with tablet-specific spacing */
            @media (min-width: 768px) and (max-width: 1023px) {
                #<?php echo esc_attr($uid); ?> .ytrow-controls{
                    justify-content:center;
                    gap:var(--controls-spacing-tablet);
                }
            }
            
            /* Desktop: centered layout with desktop spacing */
            @media (min-width: 1024px) {
                #<?php echo esc_attr($uid); ?> .ytrow-controls{
                    justify-content:center;
                    gap:var(--controls-spacing);
                }
            }
            #<?php echo esc_attr($uid); ?> .ytrow-arrow:hover{ background:var(--arrow-hover-bg-color); box-shadow:0 4px 10px rgba(0,0,0,.12); }
            #<?php echo esc_attr($uid); ?> .ytrow-arrow:active{ transform:translateY(0); box-shadow:none; }
            
            /* Mobile: preserve positioning during active state */
            @media (max-width: 767px) {
                #<?php echo esc_attr($uid); ?> .ytrow-arrow:active{ 
                    transform:translateY(-50%) !important; /* Preserve mobile centering */
                }
                
                /* Ensure arrows are properly sized on mobile */
                #<?php echo esc_attr($uid); ?> .ytrow-arrow span i {
                    font-size: var(--arrow-icon-size) !important;
                }
            }
            #<?php echo esc_attr($uid); ?> .ytrow-arrow span{ 
                position: relative; 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                width: 100%; 
                height: 100%; 
            }
            #<?php echo esc_attr($uid); ?> .ytrow-arrow span i{ 
                color: var(--arrow-icon-color); 
                font-size: var(--arrow-icon-size); 
                line-height: 1; 
                display: block;
                width: auto;
                height: auto;
            }

            /* Dots */
            #<?php echo esc_attr($uid); ?> .ytrow-dots{ 
                display:flex; 
                gap:10px; 
                align-items:center; 
                flex-wrap: wrap;
                justify-content: center;
                max-width: 100%;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-dot{ 
                width:10px; 
                height:10px; 
                border-radius:50%; 
                background:var(--pagination-dot-color); 
                border:0; 
                cursor:pointer; 
                padding:0; 
                flex-shrink: 0;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-dot.active{ background:var(--pagination-active-dot-color); }
            
            /* Mobile: Allow dots to wrap and ensure they don't interfere with arrows */
            @media (max-width: 767px) {
                #<?php echo esc_attr($uid); ?> .ytrow-dots{ 
                    gap: 8px;
                    margin: 0 10px;
                }
                #<?php echo esc_attr($uid); ?> .ytrow-dot{ 
                    width: 8px; 
                    height: 8px; 
                }
            }

            /* Admin refresh below controls */
            #<?php echo esc_attr($uid); ?> .ytrow-admin{ display:flex; justify-content:center; margin-top:8px; }
            #<?php echo esc_attr($uid); ?> .ytrow-refresh{ text-decoration:none; color:var(--muted); font-size:18px; background:none; border:none; cursor:pointer; }
            #<?php echo esc_attr($uid); ?> .ytrow-refresh:hover{ color:var(--ink); }

            /* Modal styles */
            #<?php echo esc_attr($uid); ?> .ytrow-modal{
                position:fixed; inset:0; z-index:9999; display:none; align-items:center; justify-content:center;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-modal-backdrop{
                position:absolute; inset:0; background:rgba(0,0,0,.8);
            }
            #<?php echo esc_attr($uid); ?> .ytrow-modal-inner{
                position:relative; background:#fff; border-radius:var(--radius); max-width:90vw; max-height:90vh;
                width:400px; height:711px; overflow:hidden;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-close{
                position:absolute; top:10px; right:10px; width:30px; height:30px; border:0; background:#000;
                border-radius:50%; cursor:pointer; font-size:20px; line-height:1; z-index:1; color:#fff;
                display:flex; align-items:center; justify-content:center; font-weight:bold;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-player{
                width:100%; height:100%;
            }
            #<?php echo esc_attr($uid); ?> .ytrow-player iframe{
                width:100%; height:100%; border:0;
            }
        </style>

        <script>
        (function(){
            const root   = document.getElementById('<?php echo esc_js($uid); ?>');
            if(!root) {
                return;
            }
            
            // Font Awesome detection and fallback handling
            function detectFontAwesome() {
                const testIcon = document.createElement('i');
                testIcon.className = 'fa-solid fa-angle-left';
                testIcon.style.position = 'absolute';
                testIcon.style.visibility = 'hidden';
                testIcon.style.fontSize = '16px';
                document.body.appendChild(testIcon);
                
                const computedStyle = window.getComputedStyle(testIcon, '::before');
                const hasIcon = computedStyle.content !== 'none' && computedStyle.content !== '';
                
                document.body.removeChild(testIcon);
                return hasIcon;
            }
            
            // Apply fallback styling if Font Awesome is not working
            if (!detectFontAwesome()) {
                const arrows = root.querySelectorAll('.ytrow-arrow');
                arrows.forEach(arrow => {
                    arrow.classList.add('fa-fallback');
                });
            }

            const strip     = root.querySelector('.ytrow-strip');
            const dotsWrap  = root.querySelector('.ytrow-dots');
            const playMode  = root.dataset.play || 'inline';
            const centerOnClick = root.dataset.centerOnClick === 'true';

            // Mouse drag with better click handling
            let isDown = false;
            let startX = 0;
            let startLeft = 0;
            let hasMoved = false;
            
            strip.addEventListener('mousedown', e => {
                isDown = true;
                hasMoved = false;
                startX = e.clientX;
                startLeft = strip.scrollLeft;
                strip.style.cursor = 'grabbing';
            });
            
            strip.addEventListener('mousemove', e => {
                if (!isDown) return;
                const deltaX = e.clientX - startX;
                if (Math.abs(deltaX) > 5) {
                    hasMoved = true;
                }
                strip.scrollLeft = startLeft - deltaX;
            });
            
            strip.addEventListener('mouseup', e => {
                if (!isDown) return;
                isDown = false;
                strip.style.cursor = '';
                
                // If we didn't move much, treat this as a click
                if (!hasMoved) {
                    handleStripClick(e);
                }
            });
            
            // Handle touch events for mobile
            strip.addEventListener('touchstart', e => {
                isDown = true;
                hasMoved = false;
                startX = e.touches[0].clientX;
                startLeft = strip.scrollLeft;
            });
            
            strip.addEventListener('touchmove', e => {
                if (!isDown) return;
                const deltaX = e.touches[0].clientX - startX;
                if (Math.abs(deltaX) > 5) {
                    hasMoved = true;
                }
                strip.scrollLeft = startLeft - deltaX;
            });
            
            strip.addEventListener('touchend', e => {
                if (!isDown) return;
                isDown = false;
                
                // If we didn't move much, treat this as a click
                if (!hasMoved) {
                    handleStripClick(e);
                }
            });

            // Player
            function ytIframe(id, autoplay){
                const p = new URLSearchParams({rel:0,modestbranding:1,playsinline:1,autoplay:autoplay?1:0, mute: autoplay?1:0});
                const f = document.createElement('iframe');
                f.src = `https://www.youtube.com/embed/${id}?${p.toString()}`;
                f.allow = "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";
                f.allowFullscreen = true;
                return f;
            }
            function playInline(card){
                const id = card.dataset.id;
                const thumb = card.querySelector('.ytrow-thumb');
                
                // If this video is already playing, stop it
                if(thumb.dataset.playing === '1') {
                    thumb.dataset.playing = '0';
                    thumb.innerHTML = '<img src="'+thumb.dataset.src+'" alt="">';
                    return;
                }
                
                // Stop any other playing videos
                strip.querySelectorAll('.ytrow-thumb[data-playing="1"]').forEach(t=>{
                    t.dataset.playing = '0';
                    t.innerHTML = '<img src="'+t.dataset.src+'" alt="">';
                });
                
                // Store the original image and start playing this video
                const img = thumb.querySelector('img');
                thumb.dataset.src = img ? img.src : '';
                thumb.dataset.playing = '1';
                thumb.innerHTML = '';
                
                const iframe = ytIframe(id, true);
                thumb.appendChild(iframe);
                
                // Scroll the video into view only if center_on_click is enabled
                if (centerOnClick) {
                    card.scrollIntoView({behavior:'smooth', inline:'center', block:'nearest'});
                }
            }
            const modal  = root.querySelector('.ytrow-modal');
            const player = root.querySelector('.ytrow-player');
            
            // Set up modal functionality if modal elements exist
            if (modal && player) {
                // Ensure modal is hidden on initialization
                modal.style.display = 'none';
                
                function playModal(card){
                    const id = card.dataset.id;
                    player.innerHTML=''; 
                    player.appendChild(ytIframe(id, true));
                    modal.style.display='flex'; 
                    document.body.style.overflow='hidden';
                }
                
                function closeModal(){ 
                    modal.style.display='none'; 
                    player.innerHTML=''; 
                    document.body.style.overflow=''; 
                }
                
                // Close modal when clicking backdrop or close button
                modal.addEventListener('click', e=>{
                    if(e.target.classList.contains('ytrow-modal-backdrop') || e.target.classList.contains('ytrow-close')) {
                        closeModal();
                    }
                });
                
                // Close modal with Escape key
                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape' && modal.style.display === 'flex') {
                        closeModal();
                    }
                });
            }
            // Handle video card clicks
            function handleCardClick(e) {
                // Get the card from either currentTarget (direct listener) or closest (delegated)
                let card = e.currentTarget;
                if (!card || !card.classList.contains('ytrow-card')) {
                    card = e.target.closest('.ytrow-card');
                }
                
                if (!card) {
                    return;
                }
                
                const videoId = card.dataset.id;
                
                if (playMode === 'popup' && modal && player) {
                    playModal(card);
                } else if (playMode === 'redirect') {
                    // Redirect to YouTube
                    window.open(`https://www.youtube.com/watch?v=${videoId}`, '_blank');
                } else {
                    // Default to inline mode
                    playInline(card);
                }
            }
            
            // Add click event listeners directly to each card
            const cards = strip.querySelectorAll('.ytrow-card');
            
            // Global click handler that works around drag issues
            document.addEventListener('click', function(e) {
                // Check if the click is within our slider
                if (root.contains(e.target)) {
                    const card = e.target.closest('.ytrow-card');
                    if (card) {
                        e.preventDefault();
                        e.stopPropagation();
                        handleCardClick(e);
                    }
                }
            });
            
            // Simple click handler function
            function handleStripClick(e) {
                // Find the clicked card
                const card = e.target.closest('.ytrow-card');
                if (card) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleCardClick(e);
                }
            }
            
            // Add multiple event listeners to each card for better compatibility
            cards.forEach((card, index) => {
                // Try multiple event types
                const events = ['click', 'mousedown', 'mouseup'];
                
                events.forEach(eventType => {
                    card.addEventListener(eventType, function(e) {
                        if (eventType === 'mousedown') {
                            // For mousedown, we need to check if it's not a drag
                            if (!hasMoved) {
                                e.preventDefault();
                                e.stopPropagation();
                                handleCardClick(e);
                            }
                        } else {
                            e.preventDefault();
                            e.stopPropagation();
                            handleCardClick(e);
                        }
                    });
                });
            });

            // Pagination
            let pageLefts = [];
            function colsFromCSS(){
                const v = parseInt(getComputedStyle(root).getPropertyValue('--cols'), 10);
                return isNaN(v) ? 1 : Math.max(1, v);
            }
            function metrics(){
                const gap   = parseFloat(getComputedStyle(strip).gap || 16);
                const card  = strip.querySelector('.ytrow-card');
                const cardW = card ? card.getBoundingClientRect().width : 0;
                const cols  = colsFromCSS();
                const pageW = cols * cardW + (cols - 1) * gap;
                const maxScroll = Math.max(0, strip.scrollWidth - strip.clientWidth);
                const pages = Math.max(1, Math.ceil(strip.children.length / cols));
                return {gap, cardW, cols, pageW, maxScroll, pages};
            }
            function buildPagination(){
                const m = metrics();
                dotsWrap.innerHTML = '';
                pageLefts = [];
                
                // Only show pagination if there are multiple pages
                if (m.pages > 1) {
                    for(let i=0;i<m.pages;i++){
                        const left = (i === m.pages - 1) ? m.maxScroll : Math.min(m.maxScroll, Math.round(i * m.pageW));
                        pageLefts.push(left);
                        const d = document.createElement('button');
                        d.type='button'; d.className='ytrow-dot';
                        d.addEventListener('click', ()=> goToPage(i));
                        dotsWrap.appendChild(d);
                        if(i === 0) d.classList.add('active');
                    }
                    // Show controls when pagination is needed
                    root.querySelector('.ytrow-controls').style.display = 'flex';
                } else {
                    // Hide controls when all videos fit in one page
                    root.querySelector('.ytrow-controls').style.display = 'none';
                }
            }
            function goToPage(i){
                const left = pageLefts[Math.max(0, Math.min(i, pageLefts.length - 1))] || 0;
                strip.scrollTo({left, behavior:'smooth'});
            }
            function activePage(){
                const x = strip.scrollLeft;
                let best = 0, bestd = Infinity;
                pageLefts.forEach((p,i)=>{ const d = Math.abs(p - x); if(d < bestd){ best=i; bestd=d; } });
                return best;
            }
            function syncActive(){
                const idx = activePage();
                [...dotsWrap.children].forEach((dot,i)=>dot.classList.toggle('active', i===idx));
            }
            root.querySelector('[data-dir="prev"]').addEventListener('click', ()=> goToPage(activePage()-1));
            root.querySelector('[data-dir="next"]').addEventListener('click', ()=> goToPage(activePage()+1));
            strip.addEventListener('scroll', ()=>{ window.requestAnimationFrame(syncActive); }, {passive:true});

            const ro = new ResizeObserver(()=> buildPagination());
            ro.observe(root);
            buildPagination();
        })();
        </script>
        <?php
        return ob_get_clean();
        
        } catch (Exception $e) {
            self::log_error('Shortcode rendering error: ' . $e->getMessage());
            return self::render_error('An error occurred while rendering the slider. Please try again later.');
        }
    }
    
    /**
     * Sanitize playlist ID
     */
    private static function sanitize_playlist_id($playlist_id) {
        if (empty($playlist_id)) {
            return '';
        }
        
        // YouTube playlist IDs are typically 34 characters starting with PL
        $playlist_id = sanitize_text_field($playlist_id);
        
        // Basic validation for YouTube playlist ID format
        if (!preg_match('/^PL[a-zA-Z0-9_-]{32}$/', $playlist_id)) {
            self::log_error('Invalid playlist ID format: ' . $playlist_id);
            return '';
        }
        
        return $playlist_id;
    }
    
    /**
     * Render error message
     */
    private static function render_error($message) {
        if (current_user_can('manage_options')) {
            return '<div class="youtube-shorts-error" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;"><strong>YouTube Shorts Error:</strong> ' . esc_html($message) . '</div>';
        }
        
        // For non-admin users, show generic message
        return '<div class="youtube-shorts-error" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; color: #6c757d;">Content temporarily unavailable.</div>';
    }

    /**
     * Get playlist videos from YouTube API with caching
     */
    private static function get_playlist_videos($playlist_id, $max_videos = 18, $cache_ttl = 86400, $api_key = '', $thumb_quality = 'medium') {
        try {
            // Validate inputs
            if (empty($playlist_id) || empty($api_key)) {
                self::log_error('Missing playlist ID or API key');
                return [];
            }
            
            // Sanitize inputs
            $playlist_id = sanitize_text_field($playlist_id);
            $max_videos = min(max(intval($max_videos), 1), self::MAX_VIDEOS_LIMIT);
            $cache_ttl = min(max(intval($cache_ttl), self::MIN_CACHE_TTL), self::MAX_CACHE_TTL);
            
            // Generate cache key
            $cache_key = self::TRANSIENT_PREFIX . md5($playlist_id . $max_videos . $cache_ttl);
            $cached_videos = get_transient($cache_key);
            
            if ($cached_videos !== false) {
                return $cached_videos;
            }

            // Build API URL with proper escaping
            $api_url = 'https://www.googleapis.com/youtube/v3/playlistItems';
            $api_params = [
                'part' => 'snippet',
                'playlistId' => $playlist_id,
                'maxResults' => $max_videos,
                'key' => $api_key
            ];
            
            $url = add_query_arg($api_params, $api_url);
            
            // Make API request with timeout and user agent
            $response = wp_remote_get($url, [
                'timeout' => 15,
                'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
                'headers' => [
                    'Accept' => 'application/json',
                    'Accept-Encoding' => 'gzip, deflate'
                ]
            ]);
            
            if (is_wp_error($response)) {
                self::log_error('YouTube API request failed: ' . $response->get_error_message());
                return [];
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                self::log_error('YouTube API returned error code: ' . $response_code);
                return [];
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                self::log_error('YouTube API returned empty response');
                return [];
            }
            
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                self::log_error('YouTube API response JSON decode error: ' . json_last_error_msg());
                return [];
            }

            if (empty($data['items']) || !is_array($data['items'])) {
                self::log_error('YouTube API returned no items or invalid format');
                return [];
            }

            // Process and sanitize video data
            $videos = [];
            foreach ($data['items'] as $item) {
                if (!isset($item['snippet']) || !isset($item['snippet']['resourceId']['videoId'])) {
                    continue; // Skip invalid items
                }
                
                $snippet = $item['snippet'];
                $video_id = sanitize_text_field($snippet['resourceId']['videoId']);
                
                // Validate video ID format
                if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $video_id)) {
                    continue; // Skip invalid video IDs
                }
                
                // Get thumbnail with quality fallback
                $thumbnail_url = '';
                
                // Try to get the requested quality, fallback to medium if not available
                if (isset($snippet['thumbnails'][$thumb_quality]['url'])) {
                    $thumbnail_url = $snippet['thumbnails'][$thumb_quality]['url'];
                } elseif (isset($snippet['thumbnails']['medium']['url'])) {
                    $thumbnail_url = $snippet['thumbnails']['medium']['url'];
                } elseif (isset($snippet['thumbnails']['high']['url'])) {
                    $thumbnail_url = $snippet['thumbnails']['high']['url'];
                } elseif (isset($snippet['thumbnails']['default']['url'])) {
                    $thumbnail_url = $snippet['thumbnails']['default']['url'];
                }
                
                $videos[] = [
                    'video_id' => $video_id,
                    'title' => sanitize_text_field($snippet['title'] ?? ''),
                    'description' => wp_trim_words(sanitize_textarea_field($snippet['description'] ?? ''), 20),
                    'thumbnail' => esc_url_raw($thumbnail_url),
                    'published_at' => sanitize_text_field($snippet['publishedAt'] ?? '')
                ];
            }
            
            // Only cache if we have valid videos
            if (!empty($videos)) {
                set_transient($cache_key, $videos, $cache_ttl);
            }
            
            return $videos;
            
        } catch (Exception $e) {
            self::log_error('Exception in get_playlist_videos: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Deletes all transients whose name starts with the given prefix.
     * Works on single-site and does not rely on WP Object Cache being enabled.
     */
    private static function purge_transients_with_prefix($prefix) {
        try {
            global $wpdb;
            
            // Validate prefix parameter
            if (empty($prefix) || !is_string($prefix)) {
                self::log_error('Invalid prefix provided for transient purge');
                return false;
            }
            
            // Sanitize prefix to prevent SQL injection
            $prefix = sanitize_text_field($prefix);
            
            // Find all option names like _transient_{prefix}* (value rows)
            $like_value = $wpdb->esc_like('_transient_' . $prefix) . '%';
            
            // Use prepared statement for security
            $option_names = $wpdb->get_col(
                $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like_value)
            );
            
            if (is_wp_error($option_names)) {
                self::log_error('Database error during transient purge: ' . $option_names->get_error_message());
                return false;
            }

            $deleted_count = 0;
            if (!empty($option_names)) {
                foreach ($option_names as $oname) {
                    // Validate option name format
                    if (!is_string($oname) || strpos($oname, '_transient_') !== 0) {
                        continue;
                    }
                    
                    $tkey = substr($oname, strlen('_transient_')); // real transient key
                    if (strpos($tkey, $prefix) === 0) {
                        if (delete_transient($tkey)) {
                            $deleted_count++;
                        }
                    }
                }
            }

            // Cleanup stray timeout rows if any remain
            $like_timeout = $wpdb->esc_like('_transient_timeout_' . $prefix) . '%';
            $wpdb->query(
                $wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout)
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("YouTube Shorts: Purged {$deleted_count} transients with prefix '{$prefix}'");
            }
            
            return true;
            
        } catch (Exception $e) {
            self::log_error('Exception during transient purge: ' . $e->getMessage());
            return false;
        }
    }


}

// Initialize the plugin
YouTube_Shorts_Slider::init();

// Add uninstall hook for cleanup
register_uninstall_hook(__FILE__, 'youtube_shorts_slider_uninstall');

/**
 * Clean up plugin data on uninstall
 */
function youtube_shorts_slider_uninstall() {
    // Remove all plugin options
    delete_option('youtube_api_key');
    delete_option('youtube_shorts_defaults');
    
    // Clear all transients
    global $wpdb;
    $like_value = $wpdb->esc_like('_transient_yt_shorts_') . '%';
    $like_timeout = $wpdb->esc_like('_transient_timeout_yt_shorts_') . '%';
    
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_value));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout));
    
    // Clear any object cache
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
