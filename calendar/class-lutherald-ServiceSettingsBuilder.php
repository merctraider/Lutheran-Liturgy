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
        
        // Always include default day
        $default_day = $this->calendar->retrieve_day_info($this->date);
        $options[] = [
            'type' => 'default',
            'display' => $this->get_default_day_display(),
            'value' => 'default',
            'data' => $default_day
        ];
        
        // Check for feast day
        $feast = $this->calendar->get_festival($this->date);
        if ($feast && !empty($feast)) {
            $options[] = [
                'type' => 'feast',
                'display' => $feast['name'] ?? 'Feast Day',
                'value' => 'feast',
                'data' => $feast
            ];
        }
        
        // Check for ember day
        // Ember days are already registered in the calendar, so check if current day has ember day designation
        if ($default_day && isset($default_day['display']) && 
            (strpos($default_day['display'], 'Ember') !== false || 
             strpos($default_day['display'], 'Rogation') !== false)) {
            $options[] = [
                'type' => 'ember',
                'display' => $default_day['display'],
                'value' => 'ember',
                'data' => $default_day
            ];
        }
        
        return $options;
    }
    
    /**
     * Get a display name for the default day
     */
    private function get_default_day_display() {
        $day_info = $this->calendar->retrieve_day_info($this->date);
        
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
        $required_fields = $ordo_config['fields'];
        
        // Check each required field
        foreach ($required_fields as $field) {
            if (!isset($settings[$field]) || empty($settings[$field])) {
                $errors[] = "Field '$field' is required";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
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