<div id="slimsnap-upload-dialog" style="display:none;">
    <div class="slimsnap-dialog-content">
        <button type="button" class="close-dialog" aria-label="<?php _e('Close', 'slimsnap-optimizer'); ?>">×</button>
        <h2><?php _e('Optimize Image', 'slimsnap-optimizer'); ?></h2>
        
        <div class="image-preview">
            <div class="image-comparison">
                <div class="comparison-slider">
                    <img src="" alt="Original" id="slimsnap-preview-original">
                    <img src="" alt="Preview" id="slimsnap-preview-optimized">
                </div>
                <input type="range" class="comparison-range" min="0" max="100" value="50">
                <div class="comparison-labels">
                    <span><?php _e('Original', 'slimsnap-optimizer'); ?></span>
                    <span><?php _e('Optimized', 'slimsnap-optimizer'); ?></span>
                </div>
            </div>
            <div class="image-info">
                <p><strong><?php _e('File Name:', 'slimsnap-optimizer'); ?></strong> <span id="slimsnap-file-name"></span></p>
                <p><strong><?php _e('Original Size:', 'slimsnap-optimizer'); ?></strong> <span id="slimsnap-original-size"></span></p>
                <p><strong><?php _e('Dimensions:', 'slimsnap-optimizer'); ?></strong> <span id="slimsnap-dimensions"></span></p>
            </div>
        </div>

        <div class="optimization-options">
            <h3><?php _e('Optimization Options', 'slimsnap-optimizer'); ?></h3>
            <label>
                <input type="radio" name="compression_type" value="lossy" checked>
                <?php _e('Lossy Compression (Smaller files, slight quality loss)', 'slimsnap-optimizer'); ?>
            </label>
            <label>
                <input type="radio" name="compression_type" value="lossless">
                <?php _e('Lossless Compression (Larger files, no quality loss)', 'slimsnap-optimizer'); ?>
            </label>
            
            <div class="quality-slider">
                <label><?php _e('Quality:', 'slimsnap-optimizer'); ?> <span id="quality-value">80</span>%</label>
                <input type="range" id="compression-quality" min="0" max="100" value="80">
            </div>
        </div>

        <div class="optimization-progress" style="display:none;">
            <div class="progress-bar">
                <div class="progress-bar-fill"></div>
            </div>
            <div class="progress-text"></div>
        </div>

        <div class="optimization-results" style="display:none;">
            <h3><?php _e('Optimization Results', 'slimsnap-optimizer'); ?></h3>
            <div class="results-grid">
                <div class="result-item">
                    <span class="label"><?php _e('Original Size:', 'slimsnap-optimizer'); ?></span>
                    <span id="result-original-size" class="value"></span>
                </div>
                <div class="result-item">
                    <span class="label"><?php _e('Optimized Size:', 'slimsnap-optimizer'); ?></span>
                    <span id="result-optimized-size" class="value"></span>
                </div>
                <div class="result-item">
                    <span class="label"><?php _e('Space Saved:', 'slimsnap-optimizer'); ?></span>
                    <span id="result-savings" class="value"></span>
                </div>
            </div>
        </div>

        <div class="dialog-buttons">
            <button type="button" class="button button-primary" id="slimsnap-optimize">
                <?php _e('Optimize & Upload', 'slimsnap-optimizer'); ?>
            </button>
            <button type="button" class="button" id="slimsnap-skip">
                <?php _e('Skip Optimization', 'slimsnap-optimizer'); ?>
            </button>
        </div>
    </div>
</div> 