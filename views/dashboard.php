<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get total number of images
global $wpdb;
$total_images = $wpdb->get_var("
    SELECT COUNT(*) 
    FROM {$wpdb->posts} 
    WHERE post_type = 'attachment' 
    AND post_mime_type LIKE 'image/%'
");

$optimizer = new SlimSnap_Optimizer();
?>
<div class="wrap">
    <h1><?php _e('SlimSnap Optimizer', 'slimsnap-optimizer'); ?></h1>
    
    <div class="optimization-panel">
        <h2><?php _e('Image Optimization', 'slimsnap-optimizer'); ?></h2>
        
        <div class="optimization-summary">
            <p>
                <?php printf(
                    __('Found %s images in your media library.', 'slimsnap-optimizer'),
                    '<strong>' . esc_html($total_images) . '</strong>'
                ); ?>
            </p>
        </div>
        
        <div class="optimization-actions">
            <button id="optimize-all" class="button button-primary">
                <?php _e('Optimize All Images', 'slimsnap-optimizer'); ?>
            </button>
            
            <button id="stop-optimization" class="button" style="display: none;">
                <?php _e('Stop Optimization', 'slimsnap-optimizer'); ?>
            </button>
        </div>
        
        <div id="optimization-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
            </div>
            <div class="progress-text"></div>
        </div>
        
        <div class="optimization-stats">
            <div class="stat-box">
                <h3><?php _e('Images Optimized', 'slimsnap-optimizer'); ?></h3>
                <p id="images-optimized">0</p>
            </div>
            <div class="stat-box">
                <h3><?php _e('Space Saved', 'slimsnap-optimizer'); ?></h3>
                <p id="space-saved">0 KB</p>
            </div>
        </div>
    </div>
</div>

<style>
.optimization-panel {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.optimization-actions {
    margin: 20px 0;
}

.progress-bar {
    height: 20px;
    background: #f0f0f0;
    border-radius: 3px;
    margin: 10px 0;
}

.progress-bar-fill {
    height: 100%;
    background: #2271b1;
    width: 0;
    border-radius: 3px;
    transition: width 0.3s ease;
}

.optimization-stats {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.stat-box {
    flex: 1;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 3px;
    text-align: center;
}

.stat-box h3 {
    margin: 0 0 10px 0;
}

.stat-box p {
    font-size: 24px;
    margin: 0;
    font-weight: bold;
    color: #2271b1;
}
</style>

<script>
jQuery(document).ready(function($) {
    let isOptimizing = false;
    let totalImages = 0;
    let processedImages = 0;
    let totalSaved = 0;
    
    $('#optimize-all').click(function() {
        if (isOptimizing) return;
        
        isOptimizing = true;
        $(this).prop('disabled', true);
        $('#stop-optimization').show();
        $('#optimization-progress').show();
        
        // Reset counters
        processedImages = 0;
        totalSaved = 0;
        $('#images-optimized').text('0');
        $('#space-saved').text('0 KB');
        
        // Start optimization process
        getUnoptimizedImages();
    });
    
    $('#stop-optimization').click(function() {
        isOptimizing = false;
        $(this).hide();
        $('#optimize-all').prop('disabled', false);
        updateProgress(processedImages, totalImages, 'Optimization stopped');
    });
    
    function getUnoptimizedImages() {
        $.post(ajaxurl, {
            action: 'get_unoptimized_images',
            nonce: '<?php echo wp_create_nonce("slimsnap_optimize"); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                totalImages = response.data.length;
                processedImages = 0;
                updateProgress(0, totalImages, 'Starting optimization...');
                
                if (totalImages > 0) {
                    optimizeNext(response.data);
                } else {
                    updateProgress(0, 0, 'No images to optimize');
                    isOptimizing = false;
                    $('#stop-optimization').hide();
                    $('#optimize-all').prop('disabled', false);
                }
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Failed to get images:', textStatus, errorThrown);
            alert('Failed to get images. Please try again.');
            isOptimizing = false;
            $('#stop-optimization').hide();
            $('#optimize-all').prop('disabled', false);
        });
    }
    
    function optimizeNext(images) {
        if (!isOptimizing || processedImages >= totalImages) {
            isOptimizing = false;
            $('#stop-optimization').hide();
            $('#optimize-all').prop('disabled', false);
            return;
        }
        
        $.post(ajaxurl, {
            action: 'optimize_image',
            attachment_id: images[processedImages],
            nonce: '<?php echo wp_create_nonce("slimsnap_optimize"); ?>'
        })
        .done(function(response) {
            if (response.success && response.data) {
                totalSaved += parseInt(response.data.savings) || 0;
                $('#space-saved').text(formatBytes(totalSaved));
            }
            
            processedImages++;
            $('#images-optimized').text(processedImages);
            updateProgress(processedImages, totalImages);
            
            if (processedImages < totalImages && isOptimizing) {
                optimizeNext(images);
            } else {
                updateProgress(processedImages, totalImages, 'Optimization complete!');
                isOptimizing = false;
                $('#stop-optimization').hide();
                $('#optimize-all').prop('disabled', false);
            }
        })
        .fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Failed to optimize image:', textStatus, errorThrown);
            processedImages++;
            if (processedImages < totalImages && isOptimizing) {
                optimizeNext(images);
            }
        });
    }
    
    function updateProgress(current, total, message = '') {
        const percent = total === 0 ? 0 : Math.round((current / total) * 100);
        $('.progress-bar-fill').css('width', percent + '%');
        $('.progress-text').text(message || `Optimizing: ${percent}% (${current}/${total})`);
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script> 