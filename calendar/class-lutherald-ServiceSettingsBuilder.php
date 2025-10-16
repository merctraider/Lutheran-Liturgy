<?php

namespace Lutherald;

class ServiceSettingsBuilder {
    private ChurchYear $calendar;
    private $date;
    private $ordo_config;
    
    public function __construct($date) {
        if (is_string($date)) {
            $this->date = new \DateTime($date);
        } else {
            $this->date = $date;
        }
        
        $this->calendar = ChurchYear::create_church_year($this->date);
        $this->ordo_config = $this->load_ordo_config();
    }
    
    /**
     * Load ordo configuration from JSON file
     */
    private function load_ordo_config() {
        $json = file_get_contents(dirname(__FILE__) . "/ordo_config.json");
        return json_decode($json, true);
    }
    
    /**
     * Get available day options for the selected date
     * Returns array with default, feast (if exists), and ember day (if exists)
     */
    public function get_day_options() {
        $options = [];
        
        // Get default day info
        $default_day = $this->calendar->retrieve_day_info($this->date);
        
        // Check for feast day FIRST
        $feast = $this->calendar->get_festival($this->date);
        
        // If there's a feast, add it as an option
        if ($feast && !empty($feast)) {
            $options[] = [
                'type' => 'feast',
                'display' => $feast['name'] ?? $feast['display'] ?? 'Feast Day',
                'value' => 'feast',
                'data' => $feast
            ];
        }
        
        // Always include default day (unless it IS the feast)
        $default_display = $this->get_default_day_display($default_day);
        
        // Only add default if it's different from feast or if there's no feast
        if (!$feast || ($feast && $default_display !== ($feast['name'] ?? $feast['display'] ?? ''))) {
            $options[] = [
                'type' => 'default',
                'display' => $default_display,
                'value' => 'default',
                'data' => $default_day
            ];
        }
        
        // Check if the default day is an ember/rogation day
        if ($default_day && isset($default_day['display'])) {
            $display_lower = strtolower($default_day['display']);
            if (strpos($display_lower, 'ember') !== false || strpos($display_lower, 'rogation') !== false) {
                // Only add as separate option if not already the default
                if (count($options) > 1 || $options[0]['type'] !== 'default') {
                    $options[] = [
                        'type' => 'ember',
                        'display' => $default_day['display'],
                        'value' => 'ember',
                        'data' => $default_day
                    ];
                }
            }
        }
        
        // If no options at all, add a basic default
        if (empty($options)) {
            $options[] = [
                'type' => 'default',
                'display' => $default_display,
                'value' => 'default',
                'data' => $default_day
            ];
        }
        
        return $options;
    }
    
    /**
     * Get a display name for the default day
     */
    private function get_default_day_display($day_info = null) {
        if (!$day_info) {
            $day_info = $this->calendar->retrieve_day_info($this->date);
        }
        
        if ($day_info && isset($day_info['display'])) {
            return $day_info['display'];
        }
        
        // Fallback to day of week
        return $this->date->format('l');
    }
    
    /**
     * Get field configuration for a specific ordo type
     */
    public function get_ordo_fields($ordo_type) {
        if (!isset($this->ordo_config[$ordo_type])) {
            return null;
        }
        
        return $this->ordo_config[$ordo_type];
    }
    
    /**
     * Get all available ordo types
     */
    public function get_ordo_types() {
        $types = [];
        foreach ($this->ordo_config as $key => $config) {
            $types[] = [
                'value' => $key,
                'label' => $config['display']
            ];
        }
        return $types;
    }
    
    /**
     * Validate settings before submission
     * Now uses array-based field configuration
     */
    public function validate_settings($settings) {
        $errors = [];
        
        // Check if ordo type exists
        if (!isset($settings['order_of_service']) || 
            !isset($this->ordo_config[$settings['order_of_service']])) {
            $errors[] = 'Invalid order of service';
            return ['valid' => false, 'errors' => $errors];
        }
        
        $ordo_config = $this->ordo_config[$settings['order_of_service']];
        $field_configs = $ordo_config['fields'];
        
        // Check each required field using the array structure
        foreach ($field_configs as $field_config) {
            $field_name = $field_config['name'];
            $is_required = $field_config['required'] ?? false;
            
            // Only validate if field is required
            if ($is_required) {
                if (!isset($settings[$field_name]) || empty($settings[$field_name])) {
                    $label = $field_config['label'] ?? $field_name;
                    $errors[] = "Field '{$label}' is required";
                }
            }
            
            // Type-specific validation
            if (isset($settings[$field_name]) && !empty($settings[$field_name])) {
                $validation_error = $this->validate_field_type($field_config, $settings[$field_name]);
                if ($validation_error) {
                    $errors[] = $validation_error;
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate individual field based on type
     */
    private function validate_field_type($field_config, $value) {
        $field_type = $field_config['type'];
        $field_name = $field_config['name'];
        $label = $field_config['label'] ?? $field_name;
        
        switch ($field_type) {
            case 'number':
                if (!is_numeric($value)) {
                    return "Field '{$label}' must be a number";
                }
                if (isset($field_config['min']) && $value < $field_config['min']) {
                    return "Field '{$label}' must be at least {$field_config['min']}";
                }
                if (isset($field_config['max']) && $value > $field_config['max']) {
                    return "Field '{$label}' must be at most {$field_config['max']}";
                }
                break;
                
            case 'select':
            case 'hymn_select':
                // Validate that value is in the options list
                if ($field_type === 'select' && isset($field_config['options'])) {
                    $valid_values = array_column($field_config['options'], 'value');
                    if (!in_array($value, $valid_values)) {
                        return "Invalid value for field '{$label}'";
                    }
                }
                break;
                
            case 'checkbox':
                // Checkboxes should be either '1' or not present
                if ($value !== '1' && $value !== 1 && $value !== true) {
                    return "Invalid value for checkbox '{$label}'";
                }
                break;
        }
        
        return null; // No error
    }
    
    /**
     * Get day info for the selected date and day type
     */
    public function get_day_info($day_type = 'default') {
        switch ($day_type) {
            case 'feast':
                return $this->calendar->get_festival($this->date);
                
            case 'ember':
            case 'default':
            default:
                return $this->calendar->retrieve_day_info($this->date);
        }
    }
}