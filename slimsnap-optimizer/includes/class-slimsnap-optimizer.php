<?php
class SlimSnap_Optimizer {
    private $settings;
    private $quality;
    private $compression_type;

    public function __construct() {
        $this->settings = get_option('slimsnap_settings', array(
            'compression_type' => 'lossy',
            'compression_quality' => 80
        ));
        
        $this->quality = $this->settings['compression_quality'];
        $this->compression_type = $this->settings['compression_type'];
    }

    public function optimize_image($file_path, $settings = array()) {
        if (!file_exists($file_path)) {
            return array(
                'success' => false,
                'message' => 'File not found'
            );
        }

        // Create backup before optimization
        $backup_path = SLIMSNAP_BACKUP_DIR . '/' . basename($file_path);
        if (!file_exists($backup_path)) {
            copy($file_path, $backup_path);
        }

        $defaults = array(
            'compression_type' => 'lossy',
            'quality' => 80
        );
        $settings = wp_parse_args($settings, $defaults);

        // Get original file size
        $original_size = filesize($file_path);

        // Get image type
        $file_type = wp_check_filetype($file_path);
        $image_type = $file_type['type'];

        // Create image from file with null coalescing
        $image = null;
        switch ($image_type) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($file_path) ?: null;
                break;
            case 'image/png':
                $image = @imagecreatefrompng($file_path) ?: null;
                break;
            case 'image/gif':
                $image = @imagecreatefromgif($file_path) ?: null;
                break;
        }

        if (!$image) {
            return array(
                'success' => false,
                'message' => 'Failed to create image resource'
            );
        }

        // Ensure proper resource handling with try-finally
        try {
            // Resize if width is larger than 1140px
            $max_width = 1140;
            $width = imagesx($image);
            $height = imagesy($image);
            
            if ($width > $max_width) {
                $ratio = $width / $height;
                $new_width = $max_width;
                $new_height = (int)round($max_width / $ratio);
                
                $resized = imagecreatetruecolor($new_width, $new_height);
                if (!$resized) {
                    throw new Exception('Failed to create resized image');
                }
                
                try {
                    // Preserve transparency for PNG
                    if ($image_type === 'image/png') {
                        imagealphablending($resized, false);
                        imagesavealpha($resized, true);
                        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                        imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
                    }
                    
                    if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height)) {
                        throw new Exception('Failed to resize image');
                    }
                    
                    imagedestroy($image);
                    $image = $resized;
                } catch (Exception $e) {
                    imagedestroy($resized);
                    throw $e;
                }
            }

            // Create temporary file with unique name
            $temp_file = $file_path . '.' . uniqid('', true) . '.tmp';

            // Enhanced saving with specific optimizations per format
            $success = false;
            switch ($image_type) {
                case 'image/jpeg':
                    $stripped = imagecreatetruecolor(imagesx($image), imagesy($image));
                    if (!$stripped) {
                        throw new Exception('Failed to create stripped image');
                    }
                    
                    try {
                        imagecopy($stripped, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                        imageinterlace($stripped, true);
                        
                        $quality = $settings['compression_type'] === 'lossy' 
                            ? min(100, (int)$settings['quality'])
                            : 92;
                            
                        $success = imagejpeg($stripped, $temp_file, $quality);
                    } finally {
                        imagedestroy($stripped);
                    }
                    break;

                case 'image/png':
                    imagealphablending($image, false);
                    imagesavealpha($image, true);
                    
                    if ($settings['compression_type'] === 'lossy' && !$this->has_transparency($image)) {
                        imagepalettetotruecolor($image);
                        imagealphablending($image, true);
                        imagesavealpha($image, false);
                        
                        $colors = max(16, min(256, (int)round(256 * $settings['quality'] / 100)));
                        imagetruecolortopalette($image, false, $colors);
                        $success = imagepng($image, $temp_file, 9);
                    } else {
                        $compression = min(9, (int)round(9 * (100 - $settings['quality']) / 100));
                        $success = imagepng($image, $temp_file, $compression);
                    }
                    break;

                case 'image/gif':
                    if ($settings['compression_type'] === 'lossy') {
                        $colors = max(32, min(256, (int)round(256 * $settings['quality'] / 100)));
                        imagetruecolortopalette($image, true, $colors);
                    }
                    $success = imagegif($image, $temp_file);
                    break;
            }

            if (!$success) {
                @unlink($temp_file);
                throw new Exception('Failed to save optimized image');
            }

            // Get optimized size
            $optimized_size = filesize($temp_file);

            // Only replace if we achieved meaningful savings (more than 5%)
            if ($optimized_size < $original_size * 0.95) {
                unlink($file_path);
                rename($temp_file, $file_path);
            } else {
                unlink($temp_file);
                $optimized_size = $original_size;
            }

            return array(
                'success' => true,
                'original_size' => $original_size,
                'optimized_size' => $optimized_size,
                'savings_percent' => round(($original_size - $optimized_size) / $original_size * 100, 2)
            );

        } finally {
            // Ensure image resource is always freed
            if (is_resource($image)) {
                imagedestroy($image);
            }
        }
    }

    private function optimize_thumbnails($attachment_id) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }

        $upload_dir = wp_upload_dir();
        $base_dir = dirname(get_attached_file($attachment_id));

        foreach ($metadata['sizes'] as $size => $size_data) {
            $file_path = $base_dir . '/' . $size_data['file'];
            if (!file_exists($file_path)) {
                continue;
            }

            // Optimize thumbnail using same settings
            $image = wp_get_image_editor($file_path);
            if (!is_wp_error($image)) {
                $image->set_quality($this->quality);
                $image->save($file_path);
            }
        }
    }

    private function has_transparency($image) {
        if (!is_resource($image)) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($image, $x, $y);
                if (($rgba & 0x7F000000) >> 24) {
                    return true;
                }
            }
        }

        return false;
    }

    private function log_optimization($attachment_id, $original_size, $optimized_size) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'slimsnap_logs',
            array(
                'attachment_id' => $attachment_id,
                'original_size' => $original_size,
                'optimized_size' => $optimized_size,
                'savings_percent' => round(($original_size - $optimized_size) / $original_size * 100, 2),
                'optimization_type' => $this->compression_type,
                'status' => 'success',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%f', '%s', '%s', '%s')
        );
    }
} 