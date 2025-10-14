<?php

namespace Lutherald;

class SettingsAPIHandler {
    
    public function __construct() {
        // Set JSON header for all responses
        header('Content-Type: application/json');
    }
    
    /**
     * Main request handler - routes based on action parameter
     */
    public function handle_request() {
        $action = $_GET['action'] ?? $_POST['action'] ?? null;
        
        if (!$action) {
            $this->send_error('No action specified');
            return;
        }
        
        try {
            switch ($action) {
                case 'check_date':
                    $this->check_date();
                    break;
                    
                case 'get_ordo_fields':
                    $this->get_ordo_fields();
                    break;
                    
                case 'get_ordo_types':
                    $this->get_ordo_types();
                    break;
                    
                case 'validate_settings':
                    $this->validate_settings();
                    break;
                    
                case 'get_day_info':
                    $this->get_day_info();
                    break;
                    
                default:
                    $this->send_error('Invalid action: ' . $action);
            }
        } catch (\Exception $e) {
            $this->send_error('Server error: ' . $e->getMessage());
        }
    }
    
    /**
     * Check what day options are available for a given date
     */
    private function check_date() {
        $date = $_GET['date'] ?? null;
        
        if (!$date) {
            $this->send_error('Date parameter is required');
            return;
        }
        
        // Validate date format
        $datetime = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$datetime || $datetime->format('Y-m-d') !== $date) {
            $this->send_error('Invalid date format. Use Y-m-d format.');
            return;
        }
        
        $builder = new ServiceSettingsBuilder($date);
        $options = $builder->get_day_options();
        
        $this->send_success($options);
    }
    
    /**
     * Get field configuration for a specific ordo type
     */
    private function get_ordo_fields() {
        $date = $_GET['date'] ?? null;
        $ordo = $_GET['ordo'] ?? null;
        
        if (!$date) {
            $this->send_error('Date parameter is required');
            return;
        }
        
        if (!$ordo) {
            $this->send_error('Ordo parameter is required');
            return;
        }
        
        $builder = new ServiceSettingsBuilder($date);
        $fields = $builder->get_ordo_fields($ordo);
        
        if ($fields === null) {
            $this->send_error('Invalid ordo type: ' . $ordo);
            return;
        }
        
        $this->send_success($fields);
    }
    
    /**
     * Get all available ordo types
     */
    private function get_ordo_types() {
        $date = $_GET['date'] ?? date('Y-m-d');
        
        $builder = new ServiceSettingsBuilder($date);
        $types = $builder->get_ordo_types();
        
        $this->send_success($types);
    }
    
    /**
     * Validate submitted settings
     */
    private function validate_settings() {
        $date = $_POST['date'] ?? $_GET['date'] ?? null;
        
        if (!$date) {
            $this->send_error('Date parameter is required');
            return;
        }
        
        // Get all settings from POST/GET
        $settings = array_merge($_GET, $_POST);
        
        $builder = new ServiceSettingsBuilder($date);
        $validation = $builder->validate_settings($settings);
        
        if ($validation['valid']) {
            $this->send_success(['valid' => true]);
        } else {
            $this->send_error('Validation failed', $validation['errors']);
        }
    }
    
    /**
     * Get detailed day information
     */
    private function get_day_info() {
        $date = $_GET['date'] ?? null;
        $day_type = $_GET['day_type'] ?? 'default';
        
        if (!$date) {
            $this->send_error('Date parameter is required');
            return;
        }
        
        $builder = new ServiceSettingsBuilder($date);
        $day_info = $builder->get_day_info($day_type);
        
        $this->send_success($day_info);
    }
    
    /**
     * Send successful JSON response
     */
    private function send_success($data) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
        exit;
    }
    
    /**
     * Send error JSON response
     */
    private function send_error($message, $details = null) {
        $response = [
            'success' => false,
            'error' => $message
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        echo json_encode($response);
        exit;
    }
}