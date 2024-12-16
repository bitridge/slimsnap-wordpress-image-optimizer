<?php
class SlimSnap_Backup {
    public function backup_image($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }

        $backup_dir = SLIMSNAP_BACKUP_DIR;
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        $file_name = basename($file_path);
        $backup_path = $backup_dir . '/' . $file_name;

        return copy($file_path, $backup_path);
    }

    public function restore_image($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) {
            return array(
                'success' => false,
                'message' => 'Attachment not found'
            );
        }

        $file_name = basename($file_path);
        $backup_path = SLIMSNAP_BACKUP_DIR . '/' . $file_name;

        if (!file_exists($backup_path)) {
            return array(
                'success' => false,
                'message' => 'Backup not found'
            );
        }

        if (copy($backup_path, $file_path)) {
            return array(
                'success' => true,
                'message' => 'Image restored successfully'
            );
        }

        return array(
            'success' => false,
            'message' => 'Failed to restore image'
        );
    }
} 