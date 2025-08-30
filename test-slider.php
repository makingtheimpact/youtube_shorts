<?php
/**
 * Test file for YouTube Shorts Slider
 * 
 * This file demonstrates the new single-row slider functionality.
 * Copy and paste these examples into your WordPress posts or pages.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    echo '<h1>YouTube Shorts Slider Test Examples</h1>';
    echo '<p>This file is for demonstration purposes only. Use these shortcodes in your WordPress posts or pages.</p>';
    echo '<hr>';
}

?>

<h2>Basic Slider (Default Settings)</h2>
<p>Shows up to 20 videos in a single row with 4 columns on desktop, 3 on tablet, 2 on mobile:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID"]</code>

<hr>

<h2>Compact Slider (Fewer Videos)</h2>
<p>Shows up to 12 videos with 3 columns on desktop, 2 on tablet, 1 on mobile:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" max="12" cols_desktop="3" cols_tablet="2" cols_mobile="1"]</code>

<hr>

<h2>Wide Slider (More Videos Per Row)</h2>
<p>Shows up to 30 videos with 6 columns on desktop, 4 on tablet, 3 on mobile:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" max="30" cols_desktop="6" cols_tablet="4" cols_mobile="3"]</code>

<hr>

<h2>Modal/Popup Mode</h2>
<p>Videos open in a popup overlay instead of embedding inline:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" play="popup"]</code>

<hr>

<h2>Redirect Mode</h2>
<p>Clicking videos redirects to YouTube (useful for mobile or when you want to avoid embedding):</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" play="redirect"]</code>

<hr>

<h2>Custom Thumbnail Height</h2>
<p>Set a specific thumbnail height (120px, 180px, or 240px):</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" thumb_height="180"]</code>

<hr>

<h2>High Quality Thumbnails</h2>
<p>Use maximum resolution thumbnails for better quality:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" thumb_quality="maxres"]</code>

<hr>

<h2>Custom Styling</h2>
<p>Customize border radius, title colors, and control spacing:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" border_radius="20" title_color="#0066cc" title_hover_color="#003366" controls_spacing="40"]</code>

<hr>

<h2>Compact Controls</h2>
<p>Reduce spacing between navigation controls:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" controls_spacing="30"]</code>

<hr>

<h2>Responsive Control Spacing</h2>
<p>Different spacing for tablet, desktop, and mobile screens:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" controls_spacing="80" controls_spacing_tablet="40" controls_spacing_mobile="30"]</code>

<h2>Custom Bottom Spacing</h2>
<p>Adjust space between slider and controls:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" controls_bottom_spacing="40"]</code>

<hr>

<h2>Custom Gap and Width</h2>
<p>Adjust spacing between videos and maximum container width:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" gap="20" max_width="1200"]</code>

<hr>

<h2>Short Cache Duration (For Testing)</h2>
<p>Set cache to 1 hour for testing new playlists:</p>
<code>[shorts_slider playlist="YOUR_PLAYLIST_ID" cache_ttl="3600"]</code>

<hr>

<h2>Backward Compatibility</h2>
<p>Old shortcode format still works:</p>
<code>[shorts_slider playlist_id="YOUR_PLAYLIST_ID" max_videos="10"]</code>

<hr>

<h2>Slider Features</h2>
<ul>
    <li><strong>Single Row Layout:</strong> All videos display in one horizontal row</li>
    <li><strong>Pagination Dots:</strong> Shows current position and total pages</li>
    <li><strong>Navigation Arrows:</strong> Previous/Next buttons for easy navigation</li>
    <li><strong>Touch Support:</strong> Swipe left/right on mobile devices</li>
    <li><strong>Mouse Drag:</strong> Click and drag to scroll on desktop</li>
    <li><strong>Responsive:</strong> Automatically adjusts columns for different screen sizes</li>
    <li><strong>Video Playback:</strong> Inline embedding, modal popup, or YouTube redirect</li>
</ul>

<h2>How to Use</h2>
<ol>
    <li>Replace <code>YOUR_PLAYLIST_ID</code> with your actual YouTube playlist ID</li>
    <li>Copy the shortcode into any WordPress post, page, or widget</li>
    <li>The slider will automatically load and display your videos</li>
    <li>Use the navigation controls to browse through all videos</li>
</ol>

<h2>Finding Your Playlist ID</h2>
<p>Your playlist ID is in the YouTube URL:</p>
<ul>
    <li>Go to your YouTube playlist</li>
    <li>Copy the URL (e.g., <code>https://www.youtube.com/playlist?list=PLxxxxxxxx</code>)</li>
    <li>The part after <code>list=</code> is your playlist ID (e.g., <code>PLxxxxxxxx</code>)</li>
</ul>

<?php if (!defined('ABSPATH')): ?>
<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
hr { margin: 30px 0; border: none; border-top: 1px solid #ddd; }
h2 { color: #333; margin-top: 30px; }
ul, ol { line-height: 1.6; }
</style>
<?php endif; ?>
