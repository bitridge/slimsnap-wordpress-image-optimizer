<?php
class SlimSnap_Stats {
    public function get_stats() {
        global $wpdb;

        return array(
            'total_optimized' => $this->get_total_optimized(),
            'total_savings' => $this->get_total_savings(),
            'total_backups' => $this->get_total_backups()
        );
    }

    private function get_total_optimized() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}slimsnap_logs WHERE status = 'success'");
    }

    private function get_total_savings() {
        global $wpdb;
        return $wpdb->get_var("SELECT SUM(original_size - optimized_size) FROM {$wpdb->prefix}slimsnap_logs WHERE status = 'success'");
    }

    private function get_total_backups() {
        $backup_dir = SLIMSNAP_BACKUP_DIR;
        if (!file_exists($backup_dir)) {
            return 0;
        }
        return count(glob($backup_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE));
    }
} 