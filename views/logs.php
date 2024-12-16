<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$logs = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}slimsnap_logs ORDER BY created_at DESC LIMIT 50");
?>

<div class="wrap">
    <h1><?php _e('Optimization Logs', 'slimsnap-optimizer'); ?></h1>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Image', 'slimsnap-optimizer'); ?></th>
                <th><?php _e('Original Size', 'slimsnap-optimizer'); ?></th>
                <th><?php _e('Optimized Size', 'slimsnap-optimizer'); ?></th>
                <th><?php _e('Savings', 'slimsnap-optimizer'); ?></th>
                <th><?php _e('Status', 'slimsnap-optimizer'); ?></th>
                <th><?php _e('Date', 'slimsnap-optimizer'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo get_the_title($log->attachment_id); ?></td>
                        <td><?php echo size_format($log->original_size); ?></td>
                        <td><?php echo size_format($log->optimized_size); ?></td>
                        <td><?php echo $log->savings_percent; ?>%</td>
                        <td><?php echo esc_html($log->status); ?></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($log->created_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6"><?php _e('No optimization logs found.', 'slimsnap-optimizer'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div> 