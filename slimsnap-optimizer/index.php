<?php
/*
Plugin Name: SlimSnap Optimizer
Description: A powerful image optimization plugin for WordPress that automatically compresses and optimizes your media library images. Features include:
- Automatic image resizing to 1140px width while maintaining aspect ratio
- Bulk optimization with real-time progress tracking
- Lossy and lossless compression options
- Quality control slider (0-100%)
- Before/after comparison preview
- Backup of original images
- Detailed optimization statistics
- Media library integration with size columns
- One-click optimization reversion
- Support for JPEG, PNG, and GIF formats
- Interactive image preview modal
- Clean, modern interface
Version: 1.1.0
Requires at least: 5.8
Requires PHP: 8.0
Author: Hasan Zaheer
Author URI: https://technotch.dev
License: GPL v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
Text Domain: slimsnap-optimizer
Domain Path: /languages

Changelog:
1.1.0
- Added image preview modal in manage images section
- Fixed optimization statistics display
- Improved compression algorithm for better results
- Added automatic resizing to 1140px width
- Enhanced PHP 8.0+ compatibility
- Added media library size columns
- Improved error handling and logging

1.0.0
- Initial release
- Basic image optimization
- Bulk optimization feature
- Settings page
- Media library integration
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SLIMSNAP_VERSION', '1.1.0');
define('SLIMSNAP_PATH', plugin_dir_path(__FILE__));
define('SLIMSNAP_URL', plugin_dir_url(__FILE__));
define('SLIMSNAP_BACKUP_DIR', WP_CONTENT_DIR . '/slimsnap-backups');

class SlimSnap_Plugin {
    private static $instance = null;
    private $optimizer;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Add upload hooks
        add_filter('wp_handle_upload', array($this, 'handle_upload'), 10, 2);
        add_filter('wp_generate_attachment_metadata', array($this, 'handle_attachment_metadata'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        add_action('wp_ajax_slimsnap_pre_upload_optimize', array($this, 'ajax_pre_upload_optimize'));
    }

    public function init() {
        // Load required files
        require_once SLIMSNAP_PATH . 'includes/class-slimsnap-settings.php';
        require_once SLIMSNAP_PATH . 'includes/class-slimsnap-optimizer.php';
        require_once SLIMSNAP_PATH . 'includes/class-slimsnap-stats.php';
        require_once SLIMSNAP_PATH . 'includes/class-slimsnap-backup.php';
        
        // Initialize optimizer
        $this->optimizer = new SlimSnap_Optimizer();
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add AJAX handlers
        add_action('wp_ajax_get_unoptimized_images', array($this, 'ajax_get_unoptimized_images'));
        add_action('wp_ajax_optimize_image', array($this, 'ajax_optimize_image'));
        add_action('wp_ajax_bulk_optimize', array($this, 'ajax_bulk_optimize'));
        add_action('wp_ajax_get_optimization_stats', array($this, 'ajax_get_optimization_stats'));
        add_action('wp_ajax_get_optimized_images', array($this, 'ajax_get_optimized_images'));
        add_action('wp_ajax_revert_optimization', array($this, 'ajax_revert_optimization'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add media library columns
        add_filter('manage_media_columns', array($this, 'add_media_columns'));
        add_action('manage_media_custom_column', array($this, 'manage_media_custom_column'), 10, 2);
        add_filter('manage_upload_sortable_columns', array($this, 'register_sortable_columns'));
    }

    public function register_settings() {
        register_setting(
            'slimsnap_settings',
            'slimsnap_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        $sanitized['auto_optimize'] = !empty($input['auto_optimize']);
        $sanitized['compression_type'] = in_array($input['compression_type'], array('lossy', 'lossless')) 
            ? $input['compression_type'] 
            : 'lossy';
        $sanitized['compression_quality'] = min(100, max(0, intval($input['compression_quality'])));
        $sanitized['backup_original'] = !empty($input['backup_original']);
        
        return $sanitized;
    }

    public function activate() {
        // Create backup directory if it doesn't exist
        if (!file_exists(SLIMSNAP_BACKUP_DIR)) {
            wp_mkdir_p(SLIMSNAP_BACKUP_DIR);
            // Add an index.php file to prevent directory listing
            file_put_contents(SLIMSNAP_BACKUP_DIR . '/index.php', '<?php // Silence is golden');
        }
        
        // Create logs table
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}slimsnap_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) NOT NULL,
            original_size bigint(20) NOT NULL,
            optimized_size bigint(20) NOT NULL,
            savings_percent decimal(5,2) NOT NULL,
            optimization_type varchar(20) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY attachment_id (attachment_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Add cleanup event
        if (!wp_next_scheduled('slimsnap_cleanup_temp_files')) {
            wp_schedule_event(time(), 'hourly', 'slimsnap_cleanup_temp_files');
        }
    }

    public function add_admin_menu() {
        // Define SVG icon - lightning bolt with optimization arrows
        $icon_svg = 'data:image/svg+xml;base64,' . base64_encode('
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 2l3 3M6 22l-3-3" stroke-width="2" stroke-linecap="round"/>
                <path d="M21 5l-3 3M3 19l3-3" stroke-width="2" stroke-linecap="round"/>
            </svg>
        ');

        // Add main menu item that points to Manage Images
        add_menu_page(
            'SlimSnap Optimizer',
            'SlimSnap',
            'manage_options',
            'slimsnap-manage', // Changed from slimsnap-optimizer to slimsnap-manage
            array($this, 'render_manage'),
            $icon_svg,
            30
        );
        
        // Add Manage Images as submenu
        add_submenu_page(
            'slimsnap-manage', // Changed parent slug
            'Manage Images',
            'Manage Images',
            'manage_options',
            'slimsnap-manage' // Same as parent to make it the default page
        );
        
        // Add Settings as submenu
        add_submenu_page(
            'slimsnap-manage', // Changed parent slug
            'Settings',
            'Settings',
            'manage_options',
            'slimsnap-settings',
            array($this, 'render_settings')
        );
    }

    public function render_settings() {
        require_once SLIMSNAP_PATH . 'views/settings.php';
    }

    public function render_manage() {
        require_once SLIMSNAP_PATH . 'views/manage.php';
    }

    // Handle real-time image optimization during upload
    public function handle_upload($upload, $context = 'upload') {
        // Only process image uploads
        if (strpos($upload['type'], 'image') !== 0) {
            return $upload;
        }

        // Check if optimization was requested
        if (isset($_REQUEST['optimize_image']) && $_REQUEST['optimize_image'] === 'yes') {
            $file_path = $upload['file'];
            
            // Get optimization settings from request
            $compression_type = isset($_REQUEST['compression_type']) ? sanitize_text_field($_REQUEST['compression_type']) : 'lossy';
            $quality = isset($_REQUEST['quality']) ? intval($_REQUEST['quality']) : 80;
            
            // Backup original if enabled
            if (!empty($this->settings['backup_original'])) {
                $backup_path = SLIMSNAP_BACKUP_DIR . '/' . basename($file_path);
                copy($file_path, $backup_path);
            }
            
            $result = $this->optimizer->optimize_image($file_path, array(
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            if ($result['success']) {
                // Update file size in upload info
                $upload['size'] = filesize($file_path);
                
                // Store optimization info in attachment metadata
                add_action('add_attachment', function($attachment_id) use ($result, $compression_type, $quality) {
                    update_post_meta($attachment_id, '_slimsnap_optimized', true);
                    update_post_meta($attachment_id, '_slimsnap_original_size', $result['original_size']);
                    update_post_meta($attachment_id, '_slimsnap_optimized_size', $result['optimized_size']);
                    update_post_meta($attachment_id, '_slimsnap_savings_percent', $result['savings_percent']);
                    update_post_meta($attachment_id, '_slimsnap_settings', array(
                        'compression_type' => $compression_type,
                        'quality' => $quality
                    ));
                    
                    // Update global statistics
                    $this->update_statistics($result);
                });
            }
        }

        return $upload;
    }

    // Handle optimization of generated image sizes
    public function handle_attachment_metadata($metadata, $attachment_id) {
        // Check if this is an optimized image
        if (!get_post_meta($attachment_id, '_slimsnap_optimized', true)) {
            return $metadata;
        }

        // Get optimization settings
        $compression_type = isset($_REQUEST['compression_type']) ? sanitize_text_field($_REQUEST['compression_type']) : 'lossy';
        $quality = isset($_REQUEST['quality']) ? intval($_REQUEST['quality']) : 80;

        // Get upload directory info
        $upload_dir = wp_upload_dir();

        // Optimize each image size
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                $file_path = path_join($upload_dir['basedir'], dirname($metadata['file'])) . '/' . $size_data['file'];
                $this->optimizer->optimize_image($file_path, array(
                    'compression_type' => $compression_type,
                    'quality' => $quality
                ));
            }
        }

        return $metadata;
    }

    // AJAX handler for getting unoptimized images
    public function ajax_get_unoptimized_images() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        $compression_type = isset($_POST['compression_type']) ? sanitize_text_field($_POST['compression_type']) : 'lossy';
        
        global $wpdb;
        $images = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_opt ON p.ID = pm_opt.post_id 
                AND pm_opt.meta_key = '_slimsnap_optimized'
            LEFT JOIN {$wpdb->postmeta} pm_settings ON p.ID = pm_settings.post_id 
                AND pm_settings.meta_key = '_slimsnap_settings'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND (
                pm_opt.meta_value IS NULL
                OR NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = p.ID 
                    AND meta_key = '_slimsnap_settings'
                    AND meta_value LIKE %s
                )
            )
            ORDER BY p.ID DESC
        ", '%' . $wpdb->esc_like('"quality":' . $quality . ',"compression_type":"' . $compression_type . '"') . '%'));
        
        wp_send_json_success($images);
    }

    // AJAX handler for optimizing single image
    public function ajax_optimize_image() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        
        if (!isset($_POST['attachment_id'])) {
            wp_send_json_error(['message' => 'No attachment ID provided']);
            return;
        }
        
        $attachment_id = intval($_POST['attachment_id']);
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path) {
            wp_send_json_error(['message' => 'File not found']);
            return;
        }

        // Get optimization settings from request
        $compression_type = isset($_POST['compression_type']) ? sanitize_text_field($_POST['compression_type']) : 'lossy';
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        
        // Backup original if enabled
        if (!empty($this->settings['backup_original'])) {
            $backup_path = SLIMSNAP_BACKUP_DIR . '/' . basename($file_path);
            copy($file_path, $backup_path);
        }
        
        $result = $this->optimizer->optimize_image($file_path, array(
            'compression_type' => $compression_type,
            'quality' => $quality
        ));
        
        if ($result['success']) {
            // Store optimization info in attachment metadata
            update_post_meta($attachment_id, '_slimsnap_optimized', true);
            update_post_meta($attachment_id, '_slimsnap_original_size', $result['original_size']);
            update_post_meta($attachment_id, '_slimsnap_optimized_size', $result['optimized_size']);
            update_post_meta($attachment_id, '_slimsnap_savings_percent', $result['savings_percent']);
            update_post_meta($attachment_id, '_slimsnap_settings', array(
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            // Update global statistics
            $this->update_statistics($result);
            
            // Also optimize thumbnails
            $this->optimize_thumbnails($attachment_id, array(
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    private function optimize_thumbnails($attachment_id, $settings) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }

        $file_path = get_attached_file($attachment_id);
        $base_dir = dirname($file_path);

        foreach ($metadata['sizes'] as $size => $size_data) {
            $thumb_path = $base_dir . '/' . $size_data['file'];
            if (!file_exists($thumb_path)) {
                continue;
            }

            $result = $this->optimizer->optimize_image($thumb_path, $settings);
            
            if ($result['success']) {
                // Update thumbnail metadata
                $metadata['sizes'][$size]['filesize'] = $result['optimized_size'];
            }
        }

        // Save updated metadata with thumbnail information
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=slimsnap-settings') . '">' . __('Settings', 'slimsnap-optimizer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_media_scripts($hook) {
        if ('media-new.php' !== $hook && 'post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'slimsnap-media',
            SLIMSNAP_URL . 'assets/css/media-upload.css',
            array(),
            SLIMSNAP_VERSION
        );

        wp_enqueue_script(
            'slimsnap-media',
            SLIMSNAP_URL . 'assets/js/media-upload.js',
            array('jquery'),
            SLIMSNAP_VERSION,
            true
        );

        // Add dialog template
        add_action('admin_footer', array($this, 'add_dialog_template'));

        wp_localize_script('slimsnap-media', 'slimsnapMedia', array(
            'nonce' => wp_create_nonce('slimsnap_optimize')
        ));

        wp_add_inline_style('slimsnap-admin', '
            .column-file_size,
            .column-optimization {
                width: 10%;
            }
            .slimsnap-savings {
                color: #46b450;
                font-weight: 600;
            }
            .slimsnap-not-optimized {
                color: #dc3232;
                font-style: italic;
            }
            .column-optimization small {
                color: #666;
                display: block;
                margin-top: 2px;
            }
        ');
    }

    public function add_dialog_template() {
        echo '<script type="text/template" id="slimsnap-upload-dialog-template">';
        require_once SLIMSNAP_PATH . 'views/upload-dialog.php';
        echo '</script>';
    }

    public function ajax_pre_upload_optimize() {
        check_ajax_referer('slimsnap_optimize', 'nonce');

        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file provided']);
            return;
        }

        $file = $_FILES['file'];
        $upload_dir = wp_upload_dir();
        $temp_file = $upload_dir['basedir'] . '/slimsnap-temp-' . basename($file['name']);
        $preview_file = $upload_dir['basedir'] . '/slimsnap-preview-' . basename($file['name']);

        // Move uploaded file to temporary location
        if (move_uploaded_file($file['tmp_name'], $temp_file)) {
            // Create a copy for preview
            copy($temp_file, $preview_file);
            
            // Get optimization settings from request
            $compression_type = isset($_POST['compression_type']) ? sanitize_text_field($_POST['compression_type']) : 'lossy';
            $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
            
            // Store original file info
            $original_size = filesize($temp_file);
            $original_url = $this->create_temp_url($temp_file);
            
            // Create preview with current settings
            $preview_result = $this->optimizer->optimize_image($preview_file, array(
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            // Optimize the actual file
            $result = $this->optimizer->optimize_image($temp_file, array(
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            if ($result['success']) {
                $preview_url = $this->create_temp_url($preview_file);
                
                wp_send_json_success([
                    'message' => 'Image optimized successfully',
                    'original_size' => $result['original_size'],
                    'optimized_size' => $result['optimized_size'],
                    'savings_percent' => $result['savings_percent'],
                    'original_url' => $original_url,
                    'preview_url' => $preview_url,
                    'settings' => [
                        'compression_type' => $compression_type,
                        'quality' => $quality
                    ]
                ]);
            } else {
                wp_send_json_error(['message' => $result['message']]);
            }
            
            // Schedule cleanup of temporary files
            wp_schedule_single_event(time() + 3600, 'slimsnap_cleanup_temp_files', array(
                $temp_file,
                $preview_file
            ));
        } else {
            wp_send_json_error(['message' => 'Failed to process file']);
        }
    }

    private function create_temp_url($file_path) {
        $upload_dir = wp_upload_dir();
        return str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file_path);
    }

    private function update_statistics($result) {
        $stats = get_option('slimsnap_statistics', array(
            'total_optimized' => 0,
            'total_saved' => 0,
            'average_savings' => 0
        ));
        
        $stats['total_optimized']++;
        $stats['total_saved'] += ($result['original_size'] - $result['optimized_size']);
        $stats['average_savings'] = $stats['total_saved'] / $stats['total_optimized'];
        
        update_option('slimsnap_statistics', $stats);
    }

    public function revert_optimization($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $backup_path = SLIMSNAP_BACKUP_DIR . '/' . basename($file_path);
        
        if (file_exists($backup_path)) {
            // Restore original file
            copy($backup_path, $file_path);
            unlink($backup_path);
            
            // Remove optimization metadata
            delete_post_meta($attachment_id, '_slimsnap_optimized');
            delete_post_meta($attachment_id, '_slimsnap_original_size');
            delete_post_meta($attachment_id, '_slimsnap_optimized_size');
            delete_post_meta($attachment_id, '_slimsnap_savings_percent');
            delete_post_meta($attachment_id, '_slimsnap_settings');
            
            return true;
        }
        
        return false;
    }

    public function cleanup_temp_files($temp_file = null, $preview_file = null) {
        if ($temp_file && file_exists($temp_file)) {
            @unlink($temp_file);
        }
        if ($preview_file && file_exists($preview_file)) {
            @unlink($preview_file);
        }
        
        // Clean up old temporary files
        $upload_dir = wp_upload_dir();
        $files = glob($upload_dir['basedir'] . '/slimsnap-*');
        if ($files) {
            foreach ($files as $file) {
                if (filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }
    }

    public function bulk_optimize_images($args = array()) {
        $defaults = array(
            'limit' => 10,
            'offset' => 0,
            'compression_type' => 'lossy',
            'quality' => 80
        );
        
        $args = wp_parse_args($args, $defaults);
        
        global $wpdb;
        $images = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_opt ON p.ID = pm_opt.post_id 
                AND pm_opt.meta_key = '_slimsnap_optimized'
            LEFT JOIN {$wpdb->postmeta} pm_settings ON p.ID = pm_settings.post_id 
                AND pm_settings.meta_key = '_slimsnap_settings'
            WHERE p.post_type = 'attachment' 
            AND p.post_mime_type LIKE 'image/%'
            AND (
                pm_opt.meta_value IS NULL
                OR NOT EXISTS (
                    SELECT 1 FROM {$wpdb->postmeta} 
                    WHERE post_id = p.ID 
                    AND meta_key = '_slimsnap_settings'
                    AND meta_value LIKE %s
                )
            )
            ORDER BY p.ID DESC
            LIMIT %d OFFSET %d
        ", 
            '%' . $wpdb->esc_like('"quality":' . $args['quality'] . ',"compression_type":"' . $args['compression_type'] . '"') . '%',
            $args['limit'], 
            $args['offset']
        ));
        
        $results = array(
            'success' => array(),
            'failed' => array(),
            'total_saved' => 0
        );
        
        foreach ($images as $attachment_id) {
            $file_path = get_attached_file($attachment_id);
            if (!$file_path || !file_exists($file_path)) {
                $results['failed'][] = array(
                    'id' => $attachment_id,
                    'error' => 'File not found'
                );
                continue;
            }
            
            try {
                $result = $this->optimizer->optimize_image($file_path, array(
                    'compression_type' => $args['compression_type'],
                    'quality' => $args['quality']
                ));
                
                if ($result['success']) {
                    $results['success'][] = array(
                        'id' => $attachment_id,
                        'savings' => $result['original_size'] - $result['optimized_size']
                    );
                    $results['total_saved'] += $result['original_size'] - $result['optimized_size'];
                    
                    // Store settings with the optimization
                    update_post_meta($attachment_id, '_slimsnap_settings', array(
                        'quality' => $args['quality'],
                        'compression_type' => $args['compression_type']
                    ));
                    
                    update_post_meta($attachment_id, '_slimsnap_optimized', true);
                    update_post_meta($attachment_id, '_slimsnap_original_size', $result['original_size']);
                    update_post_meta($attachment_id, '_slimsnap_optimized_size', $result['optimized_size']);
                    update_post_meta($attachment_id, '_slimsnap_savings_percent', $result['savings_percent']);
                    
                    // Update WordPress media library metadata
                    $metadata = wp_get_attachment_metadata($attachment_id);
                    if ($metadata) {
                        // Update file size in metadata
                        $metadata['filesize'] = $result['optimized_size'];
                        
                        // Regenerate metadata for the main file and thumbnails
                        $new_metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
                        if ($new_metadata) {
                            $metadata = array_merge($metadata, $new_metadata);
                        }
                        
                        // Save updated metadata
                        wp_update_attachment_metadata($attachment_id, $metadata);
                    }
                    
                    // Also optimize thumbnails
                    $this->optimize_thumbnails($attachment_id, array(
                        'compression_type' => $args['compression_type'],
                        'quality' => $args['quality']
                    ));
                    
                    // Clear any caches
                    clean_post_cache($attachment_id);
                    
                } else {
                    $results['failed'][] = array(
                        'id' => $attachment_id,
                        'error' => $result['message']
                    );
                }
            } catch (Exception $e) {
                $results['failed'][] = array(
                    'id' => $attachment_id,
                    'error' => $e->getMessage()
                );
            }
        }
        
        return $results;
    }

    public function ajax_bulk_optimize() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        error_log('Starting AJAX bulk optimize');
        
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 10;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $compression_type = isset($_POST['compression_type']) ? sanitize_text_field($_POST['compression_type']) : 'lossy';
        $quality = isset($_POST['quality']) ? intval($_POST['quality']) : 80;
        
        error_log('Bulk optimize parameters: ' . print_r([
            'batch_size' => $batch_size,
            'offset' => $offset,
            'compression_type' => $compression_type,
            'quality' => $quality
        ], true));
        
        try {
            $results = $this->bulk_optimize_images(array(
                'limit' => $batch_size,
                'offset' => $offset,
                'compression_type' => $compression_type,
                'quality' => $quality
            ));
            
            error_log('Bulk optimize success, sending response');
            wp_send_json_success($results);
        } catch (Exception $e) {
            error_log('Bulk optimize failed: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Optimization failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ]);
        }
    }

    public function get_optimization_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(DISTINCT p.ID) as total_images,
                SUM(CASE WHEN pm_opt.meta_value = '1' THEN 1 ELSE 0 END) as optimized_images,
                COALESCE(SUM(CAST(pm_orig.meta_value AS UNSIGNED) - CAST(pm_opt_size.meta_value AS UNSIGNED)), 0) as total_saved,
                COALESCE(
                    ROUND(
                        AVG(
                            CASE 
                                WHEN pm_orig.meta_value > 0 
                                THEN ((CAST(pm_orig.meta_value AS UNSIGNED) - CAST(pm_opt_size.meta_value AS UNSIGNED)) * 100.0 / CAST(pm_orig.meta_value AS UNSIGNED))
                                ELSE 0 
                            END
                        ),
                        1
                    ),
                    0
                ) as average_savings
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm_opt ON p.ID = pm_opt.post_id 
                AND pm_opt.meta_key = '_slimsnap_optimized'
            LEFT JOIN {$wpdb->postmeta} pm_orig ON p.ID = pm_orig.post_id 
                AND pm_orig.meta_key = '_slimsnap_original_size'
            LEFT JOIN {$wpdb->postmeta} pm_opt_size ON p.ID = pm_opt_size.post_id 
                AND pm_opt_size.meta_key = '_slimsnap_optimized_size'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND pm_opt.meta_value = '1'
            AND pm_orig.meta_value > 0
        ");
        
        return array(
            'total_images' => (int)$stats->total_images,
            'optimized_images' => (int)$stats->optimized_images,
            'total_saved' => (int)$stats->total_saved,
            'average_savings' => (float)$stats->average_savings
        );
    }

    public function get_optimized_images($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, p.post_title, p.guid
            FROM {$wpdb->prefix}slimsnap_logs l
            JOIN {$wpdb->posts} p ON l.attachment_id = p.ID
            WHERE l.status = 'success'
            ORDER BY l.{$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", $args['limit'], $args['offset']));
    }

    public function ajax_get_optimization_stats() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        wp_send_json_success($this->get_optimization_stats());
    }

    public function ajax_get_optimized_images() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        global $wpdb;
        
        // Get images
        $images = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID,
                p.post_title,
                pm1.meta_value as original_size,
                pm2.meta_value as optimized_size,
                pm3.meta_value as savings_percent,
                p.post_date as date
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_slimsnap_original_size'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_slimsnap_optimized_size'
            JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_slimsnap_savings_percent'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            ORDER BY p.post_date DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        // Get total count for pagination
        $total_images = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_slimsnap_optimized'
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
        ");
        
        $formatted_images = array();
        foreach ($images as $image) {
            $thumbnail = wp_get_attachment_image_src($image->ID, 'thumbnail');
            $full_image = wp_get_attachment_image_src($image->ID, 'full');
            $formatted_images[] = array(
                'id' => $image->ID,
                'title' => $image->post_title,
                'thumbnail' => $thumbnail ? $thumbnail[0] : '',
                'full_url' => $full_image ? $full_image[0] : '',
                'original_size' => intval($image->original_size),
                'optimized_size' => intval($image->optimized_size),
                'savings_percent' => floatval($image->savings_percent),
                'date' => date('Y-m-d H:i:s', strtotime($image->date))
            );
        }
        
        wp_send_json_success(array(
            'images' => $formatted_images,
            'total_pages' => ceil($total_images / $per_page),
            'current_page' => $page
        ));
    }

    public function ajax_revert_optimization() {
        check_ajax_referer('slimsnap_optimize', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
            return;
        }
        
        // Get backup file path
        $file_path = get_attached_file($attachment_id);
        $backup_path = SLIMSNAP_BACKUP_DIR . '/' . basename($file_path);
        
        if (!file_exists($backup_path)) {
            wp_send_json_error(['message' => 'Backup file not found']);
            return;
        }
        
        // Restore original file
        if (copy($backup_path, $file_path)) {
            // Remove optimization metadata
            delete_post_meta($attachment_id, '_slimsnap_optimized');
            delete_post_meta($attachment_id, '_slimsnap_original_size');
            delete_post_meta($attachment_id, '_slimsnap_optimized_size');
            delete_post_meta($attachment_id, '_slimsnap_savings_percent');
            delete_post_meta($attachment_id, '_slimsnap_settings');
            
            // Update attachment metadata
            wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file_path));
            
            wp_send_json_success(['message' => 'Image successfully reverted']);
        } else {
            wp_send_json_error(['message' => 'Failed to restore original image']);
        }
    }

    public function add_media_columns($columns) {
        $columns['file_size'] = __('File Size', 'slimsnap-optimizer');
        $columns['optimization'] = __('Optimization', 'slimsnap-optimizer');
        return $columns;
    }

    public function manage_media_custom_column($column_name, $post_id) {
        if ($column_name === 'file_size') {
            $file_path = get_attached_file($post_id);
            if (file_exists($file_path)) {
                $size = filesize($file_path);
                echo $this->format_file_size($size);
            } else {
                echo '—';
            }
        }
        
        if ($column_name === 'optimization') {
            $is_optimized = get_post_meta($post_id, '_slimsnap_optimized', true);
            $original_size = get_post_meta($post_id, '_slimsnap_original_size', true);
            $optimized_size = get_post_meta($post_id, '_slimsnap_optimized_size', true);
            $savings_percent = get_post_meta($post_id, '_slimsnap_savings_percent', true);
            
            if ($is_optimized && $original_size && $optimized_size) {
                echo sprintf(
                    '<span class="slimsnap-savings">%s</span><br><small>%s → %s</small>',
                    sprintf(__('Saved %s%%', 'slimsnap-optimizer'), number_format($savings_percent, 1)),
                    $this->format_file_size($original_size),
                    $this->format_file_size($optimized_size)
                );
            } else {
                echo '<span class="slimsnap-not-optimized">' . __('Not optimized', 'slimsnap-optimizer') . '</span>';
            }
        }
    }

    public function register_sortable_columns($columns) {
        $columns['file_size'] = 'file_size';
        return $columns;
    }

    private function format_file_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}

// Initialize the plugin
function slimsnap_init() {
    return SlimSnap_Plugin::get_instance();
}

// Start the plugin
slimsnap_init();
