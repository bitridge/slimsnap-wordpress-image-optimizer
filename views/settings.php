<?php
if (!defined('ABSPATH')) {
    exit;
}

// Get settings
$settings = get_option('slimsnap_settings', array(
    'compression_type' => 'lossy',
    'compression_quality' => 80,
    'auto_optimize' => true,
    'backup_original' => true,
    'max_width' => 2048,
    'max_height' => 2048,
    'cpu_limit' => 50,
    'batch_size' => 5
));
?>

<div class="wrap">
    <h1><?php _e('SlimSnap Settings', 'slimsnap-optimizer'); ?></h1>
    
    <form method="post" action="options.php">
        <?php 
        settings_fields('slimsnap_settings');
        do_settings_sections('slimsnap_settings');
        ?>
        
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Real-Time Optimization', 'slimsnap-optimizer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="slimsnap_settings[auto_optimize]" value="1" 
                               <?php checked(!empty($settings['auto_optimize'])); ?>>
                        <?php _e('Automatically optimize images when uploaded', 'slimsnap-optimizer'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Compression Type', 'slimsnap-optimizer'); ?></th>
                <td>
                    <select name="slimsnap_settings[compression_type]">
                        <option value="lossy" <?php selected($settings['compression_type'], 'lossy'); ?>>
                            <?php _e('Lossy (Smaller files, slight quality loss)', 'slimsnap-optimizer'); ?>
                        </option>
                        <option value="lossless" <?php selected($settings['compression_type'], 'lossless'); ?>>
                            <?php _e('Lossless (Larger files, no quality loss)', 'slimsnap-optimizer'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Compression Quality', 'slimsnap-optimizer'); ?></th>
                <td>
                    <input type="range" name="slimsnap_settings[compression_quality]" 
                           min="0" max="100" step="1" 
                           value="<?php echo esc_attr($settings['compression_quality']); ?>"
                           oninput="this.nextElementSibling.value = this.value">
                    <output><?php echo esc_html($settings['compression_quality']); ?></output>
                    <p class="description">
                        <?php _e('0 = Maximum compression (smallest files), 100 = Minimum compression (highest quality)', 'slimsnap-optimizer'); ?>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><?php _e('Backup Original Images', 'slimsnap-optimizer'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="slimsnap_settings[backup_original]" value="1" 
                               <?php checked(!empty($settings['backup_original'])); ?>>
                        <?php _e('Create backup of original images before optimization', 'slimsnap-optimizer'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
.form-table td {
    position: relative;
}

input[type="range"] {
    width: 200px;
    vertical-align: middle;
}

output {
    display: inline-block;
    width: 40px;
    text-align: center;
    margin-left: 10px;
    vertical-align: middle;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update compression quality display in real-time
    $('input[type="range"]').on('input', function() {
        $(this).next('output').val(this.value);
    });
});
</script> 