<?php
class SlimSnap_Settings {
    private $options;
    private $option_name = 'slimsnap_settings';

    public function __construct() {
        $this->options = get_option($this->option_name, $this->get_defaults());
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting(
            'slimsnap_settings', // Option group
            $this->option_name,  // Option name
            array($this, 'sanitize_settings') // Sanitize callback
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Compression type
        $sanitized['compression_type'] = isset($input['compression_type']) 
            ? sanitize_text_field($input['compression_type']) 
            : 'lossy';
            
        // Compression quality (0-100)
        $sanitized['compression_quality'] = isset($input['compression_quality']) 
            ? intval($input['compression_quality']) 
            : 80;
            
        // Boolean settings
        $sanitized['auto_optimize'] = isset($input['auto_optimize']) 
            ? (bool) $input['auto_optimize'] 
            : true;
            
        $sanitized['backup_original'] = isset($input['backup_original']) 
            ? (bool) $input['backup_original'] 
            : true;
            
        // Integer settings
        $sanitized['max_width'] = isset($input['max_width']) 
            ? intval($input['max_width']) 
            : 2048;
            
        $sanitized['max_height'] = isset($input['max_height']) 
            ? intval($input['max_height']) 
            : 2048;
            
        $sanitized['cpu_limit'] = isset($input['cpu_limit']) 
            ? intval($input['cpu_limit']) 
            : 50;
            
        $sanitized['batch_size'] = isset($input['batch_size']) 
            ? intval($input['batch_size']) 
            : 5;

        return $sanitized;
    }

    public function init_settings() {
        if (!get_option($this->option_name)) {
            update_option($this->option_name, $this->get_defaults());
        }
    }

    public function get_settings() {
        return $this->options;
    }

    public function get_defaults() {
        return array(
            'compression_type' => 'lossy',
            'compression_quality' => 80,
            'auto_optimize' => true,
            'backup_original' => true,
            'max_width' => 2048,
            'max_height' => 2048,
            'cpu_limit' => 50,
            'batch_size' => 5
        );
    }

    public function update_settings($new_settings) {
        $this->options = wp_parse_args($new_settings, $this->get_defaults());
        update_option($this->option_name, $this->options);
    }
} 