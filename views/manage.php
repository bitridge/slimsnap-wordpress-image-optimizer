<?php
if (!defined('ABSPATH')) {
    exit;
}

$stats = $this->get_optimization_stats();
?>

<div class="wrap">
    <h1><?php _e('Manage Images', 'slimsnap-optimizer'); ?></h1>

    <div class="optimization-stats">
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?php _e('Total Images', 'slimsnap-optimizer'); ?></h3>
                <div class="stat-value"><?php echo number_format($stats['total_images']); ?></div>
            </div>
            <div class="stat-box">
                <h3><?php _e('Optimized Images', 'slimsnap-optimizer'); ?></h3>
                <div class="stat-value"><?php echo number_format($stats['optimized_images']); ?></div>
            </div>
            <div class="stat-box">
                <h3><?php _e('Total Space Saved', 'slimsnap-optimizer'); ?></h3>
                <div class="stat-value"><?php echo size_format($stats['total_saved']); ?></div>
            </div>
            <div class="stat-box">
                <h3><?php _e('Average Savings', 'slimsnap-optimizer'); ?></h3>
                <div class="stat-value"><?php echo number_format($stats['average_savings'], 1); ?>%</div>
            </div>
        </div>
    </div>

    <div class="bulk-optimization-panel">
        <h2><?php _e('Bulk Optimization', 'slimsnap-optimizer'); ?></h2>
        
        <div class="optimization-options">
            <label>
                <input type="radio" name="compression_type" value="lossy" checked>
                <?php _e('Lossy Compression (Smaller files, slight quality loss)', 'slimsnap-optimizer'); ?>
            </label>
            <label>
                <input type="radio" name="compression_type" value="lossless">
                <?php _e('Lossless Compression (Larger files, no quality loss)', 'slimsnap-optimizer'); ?>
            </label>
            
            <div class="quality-slider">
                <label>
                    <?php _e('Quality:', 'slimsnap-optimizer'); ?> 
                    <span id="quality-value">80</span>%
                </label>
                <input type="range" id="compression-quality" min="0" max="100" value="80">
            </div>
        </div>

        <div class="bulk-actions">
            <button id="start-bulk-optimization" class="button button-primary">
                <?php _e('Start Bulk Optimization', 'slimsnap-optimizer'); ?>
            </button>
            <button id="stop-bulk-optimization" class="button" style="display: none;">
                <?php _e('Stop Optimization', 'slimsnap-optimizer'); ?>
            </button>
        </div>

        <div id="bulk-progress" style="display: none;">
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
            </div>
            <div class="progress-text"></div>
            <div class="progress-stats">
                <span class="images-processed">0</span> / <span class="total-images">0</span> images processed
                (<span class="space-saved">0 KB</span> saved)
            </div>
        </div>
    </div>

    <div class="optimized-images-list">
        <h2><?php _e('Recently Optimized Images', 'slimsnap-optimizer'); ?></h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Image', 'slimsnap-optimizer'); ?></th>
                    <th><?php _e('Original Size', 'slimsnap-optimizer'); ?></th>
                    <th><?php _e('Optimized Size', 'slimsnap-optimizer'); ?></th>
                    <th><?php _e('Savings', 'slimsnap-optimizer'); ?></th>
                    <th><?php _e('Date', 'slimsnap-optimizer'); ?></th>
                    <th><?php _e('Actions', 'slimsnap-optimizer'); ?></th>
                </tr>
            </thead>
            <tbody id="optimized-images">
                <!-- Dynamically populated via JavaScript -->
            </tbody>
        </table>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="pagination-links">
                    <button class="button prev-page" disabled>‹</button>
                    <span class="paging-input">
                        <span class="current-page">1</span>
                        of
                        <span class="total-pages">1</span>
                    </span>
                    <button class="button next-page">›</button>
                </span>
            </div>
        </div>
    </div>

    <!-- Add this right after the table -->
    <div id="image-preview-modal" class="slimsnap-modal" style="display:none;">
        <div class="slimsnap-modal-content">
            <span class="close-modal">&times;</span>
            <div class="image-preview-container">
                <img src="" alt="Full size preview" id="modal-preview-image">
            </div>
            <div class="image-details">
                <p><strong><?php _e('File Name:', 'slimsnap-optimizer'); ?></strong> <span id="modal-file-name"></span></p>
                <p><strong><?php _e('Original Size:', 'slimsnap-optimizer'); ?></strong> <span id="modal-original-size"></span></p>
                <p><strong><?php _e('Optimized Size:', 'slimsnap-optimizer'); ?></strong> <span id="modal-optimized-size"></span></p>
                <p><strong><?php _e('Savings:', 'slimsnap-optimizer'); ?></strong> <span id="modal-savings"></span></p>
            </div>
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin: 20px 0;
}

.stat-box {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
    margin-top: 10px;
}

.bulk-optimization-panel {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.optimization-options label {
    display: block;
    margin: 10px 0;
}

.quality-slider {
    margin: 20px 0;
}

.progress-bar {
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    margin: 10px 0;
}

.progress-bar-fill {
    height: 100%;
    background: #2271b1;
    width: 0;
    transition: width 0.3s ease;
}

.progress-stats {
    margin-top: 10px;
    text-align: center;
    color: #666;
}

.optimized-images-list {
    margin-top: 30px;
}

.image-preview {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 4px;
}

.slimsnap-modal {
    display: none;
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
}

.slimsnap-modal-content {
    position: relative;
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 80%;
    max-width: 1200px;
    max-height: 85vh;
    overflow-y: auto;
}

.close-modal {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #666;
}

.close-modal:hover {
    color: #000;
}

.image-preview-container {
    text-align: center;
    margin: 20px 0;
}

#modal-preview-image {
    max-width: 100%;
    max-height: 60vh;
    object-fit: contain;
}

.image-details {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.image-preview {
    cursor: pointer;
    transition: transform 0.2s;
}

.image-preview:hover {
    transform: scale(1.1);
}
</style>

<script>
jQuery(document).ready(function($) {
    var isOptimizing = false;
    var batchSize = 10;
    var processedImages = 0;
    var totalImages = 0;
    var totalSaved = 0;
    var currentPage = 1;
    var totalPages = 1;

    // Load initial data
    loadOptimizedImages(1);
    updateStats();

    // Handle bulk optimization
    $('#start-bulk-optimization').click(function() {
        console.log('Starting bulk optimization...'); // Debug
        startBulkOptimization();
    });

    $('#stop-bulk-optimization').click(function() {
        stopBulkOptimization();
    });

    function startBulkOptimization() {
        isOptimizing = true;
        $('#start-bulk-optimization').hide();
        $('#stop-bulk-optimization').show();
        $('#bulk-progress').show();
        
        // Reset counters
        processedImages = 0;
        totalSaved = 0;
        
        // Get total images count with current settings
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_unoptimized_images',
                nonce: slimsnapVars.nonce,
                quality: $('#compression-quality').val(),
                compression_type: $('input[name="compression_type"]:checked').val()
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    totalImages = response.data.length;
                    $('.total-images').text(totalImages);
                    processBatch();
                } else {
                    updateProgress(0, 'No images to optimize with current settings');
                    stopBulkOptimization();
                }
            }
        });
    }

    function processBatch() {
        if (!isOptimizing) return;

        console.log('Processing batch...'); // Debug
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'bulk_optimize',
                nonce: '<?php echo wp_create_nonce("slimsnap_optimize"); ?>',
                batch_size: batchSize,
                offset: processedImages,
                compression_type: $('input[name="compression_type"]:checked').val(),
                quality: $('#compression-quality').val()
            },
            success: function(response) {
                console.log('Batch processed:', response); // Debug
                if (response.success) {
                    processedImages += response.data.success.length;
                    totalSaved += response.data.total_saved;
                    
                    updateProgress(
                        (processedImages / totalImages) * 100,
                        processedImages + ' of ' + totalImages + ' images processed'
                    );
                    
                    $('.images-processed').text(processedImages);
                    $('.space-saved').text(formatBytes(totalSaved));
                    
                    if (processedImages < totalImages && isOptimizing) {
                        processBatch();
                    } else {
                        updateProgress(100, 'Optimization complete!');
                        stopBulkOptimization();
                        updateStats();
                        loadOptimizedImages(1);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error processing batch:', error); // Debug
                alert('Failed to optimize images. Please try again.');
                stopBulkOptimization();
            }
        });
    }

    function stopBulkOptimization() {
        isOptimizing = false;
        $('#stop-bulk-optimization').hide();
        $('#start-bulk-optimization').show();
    }

    function updateProgress(percent, message) {
        $('.progress-bar-fill').css('width', percent + '%');
        $('.progress-text').text(message);
    }

    function loadOptimizedImages(page) {
        $.post(ajaxurl, {
            action: 'get_optimized_images',
            nonce: slimsnapVars.nonce,
            page: page
        }, function(response) {
            if (response.success) {
                var html = '';
                if (response.data.images.length === 0) {
                    html = '<tr><td colspan="6"><?php _e('No optimized images found.', 'slimsnap-optimizer'); ?></td></tr>';
                } else {
                    response.data.images.forEach(function(image) {
                        html += `
                            <tr>
                                <td>
                                    <img src="${image.thumbnail}" 
                                         class="image-preview" 
                                         alt=""
                                         data-full-image="${image.full_url}"
                                         data-filename="${image.title}"
                                         data-original-size="${image.original_size}"
                                         data-optimized-size="${image.optimized_size}"
                                         data-savings="${image.savings_percent}">
                                    ${image.title}
                                </td>
                                <td>${formatBytes(image.original_size)}</td>
                                <td>${formatBytes(image.optimized_size)}</td>
                                <td>${image.savings_percent.toFixed(1)}%</td>
                                <td>${image.date}</td>
                                <td>
                                    <button class="button revert-optimization" data-id="${image.id}">
                                        <?php _e('Revert', 'slimsnap-optimizer'); ?>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                }
                $('#optimized-images').html(html);
                
                // Add click handlers for image preview
                $('.image-preview').click(function() {
                    var $img = $(this);
                    $('#modal-preview-image').attr('src', $img.data('full-image'));
                    $('#modal-file-name').text($img.data('filename'));
                    $('#modal-original-size').text(formatBytes($img.data('original-size')));
                    $('#modal-optimized-size').text(formatBytes($img.data('optimized-size')));
                    $('#modal-savings').text($img.data('savings') + '%');
                    $('#image-preview-modal').fadeIn();
                });
                
                currentPage = response.data.current_page;
                totalPages = response.data.total_pages;
                updatePagination();
            }
        });
    }

    function updateStats() {
        $.post(ajaxurl, {
            action: 'get_optimization_stats',
            nonce: '<?php echo wp_create_nonce("slimsnap_optimize"); ?>'
        }, function(response) {
            if (response.success) {
                $('.stat-box:eq(0) .stat-value').text(response.data.total_images);
                $('.stat-box:eq(1) .stat-value').text(response.data.optimized_images);
                $('.stat-box:eq(2) .stat-value').text(formatBytes(response.data.total_saved));
                $('.stat-box:eq(3) .stat-value').text(response.data.average_savings.toFixed(1) + '%');
            }
        });
    }

    function updatePagination() {
        $('.current-page').text(currentPage);
        $('.total-pages').text(totalPages);
        $('.prev-page').prop('disabled', currentPage === 1);
        $('.next-page').prop('disabled', currentPage === totalPages);
    }

    $('.prev-page').click(function() {
        if (currentPage > 1) {
            loadOptimizedImages(currentPage - 1);
        }
    });

    $('.next-page').click(function() {
        if (currentPage < totalPages) {
            loadOptimizedImages(currentPage + 1);
        }
    });

    function formatBytes(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Quality slider handling
    $('#compression-quality').on('input', function() {
        $('#quality-value').text($(this).val());
    });

    // Add event listeners for settings changes
    $('#compression-quality').on('change', function() {
        // Just update the display value
        $('#quality-value').text($(this).val());
    });

    $('input[name="compression_type"]').on('change', function() {
        // No need to auto-start optimization
    });

    // Add modal close handlers
    $('.close-modal').click(function() {
        $('#image-preview-modal').fadeOut();
    });

    $(window).click(function(e) {
        if (e.target == document.getElementById('image-preview-modal')) {
            $('#image-preview-modal').fadeOut();
        }
    });

    // Add this after your existing JavaScript code
    $(document).on('click', '.revert-optimization', function() {
        var button = $(this);
        var attachmentId = button.data('id');
        
        if (confirm('<?php _e("Are you sure you want to revert this image to its original version?", "slimsnap-optimizer"); ?>')) {
            button.prop('disabled', true).text('<?php _e("Reverting...", "slimsnap-optimizer"); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'revert_optimization',
                    nonce: slimsnapVars.nonce,
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the images list and stats
                        loadOptimizedImages(currentPage);
                        updateStats();
                    } else {
                        alert(response.data.message || '<?php _e("Failed to revert optimization", "slimsnap-optimizer"); ?>');
                        button.prop('disabled', false).text('<?php _e("Revert", "slimsnap-optimizer"); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e("Failed to revert optimization", "slimsnap-optimizer"); ?>');
                    button.prop('disabled', false).text('<?php _e("Revert", "slimsnap-optimizer"); ?>');
                }
            });
        }
    });
});

var slimsnapVars = {
    nonce: '<?php echo wp_create_nonce("slimsnap_optimize"); ?>'
};
</script> 