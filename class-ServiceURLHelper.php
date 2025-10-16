<?php

class ServiceURLHelper {
    
    /**
     * Encode service settings to base64 URL parameter
     * 
     * @param array $settings Service settings array
     * @return string Base64 encoded string (URL-safe)
     */
    public static function encode($settings) {
        // Convert to JSON
        $json = json_encode($settings);
        
        // Base64 encode and make URL-safe
        $encoded = base64_encode($json);
        $urlSafe = strtr($encoded, '+/', '-_');
        $urlSafe = rtrim($urlSafe, '='); // Remove padding
        
        return $urlSafe;
    }
    
    /**
     * Decode base64 URL parameter to service settings
     * 
     * @param string $encoded Base64 encoded string
     * @return array|false Service settings array or false on failure
     */
    public static function decode($encoded) {
        // Make base64 standard again
        $base64 = strtr($encoded, '-_', '+/');
        
        // Add back padding if needed
        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }
        
        // Decode
        $json = base64_decode($base64);
        
        if ($json === false) {
            return false;
        }
        
        // Parse JSON
        $settings = json_decode($json, true);
        
        if ($settings === null) {
            return false;
        }
        
        return $settings;
    }
    
    /**
     * Get settings from request (supports both old GET params and new encoded format)
     * 
     * @return array Service settings
     */
    public static function getSettingsFromRequest() {
        // Check for new encoded parameter
        if (isset($_GET['s'])) {
            $settings = self::decode($_GET['s']);
            
            if ($settings !== false) {
                return $settings;
            }
            
            // If decode failed, fall through to old method
        }
        
        // Fall back to old GET parameters for backward compatibility
        return [
            'date' => $_GET['date'] ?? null,
            'order_of_service' => $_GET['order_of_service'] ?? null,
            'opening_hymn' => $_GET['opening_hymn'] ?? null,
            'chief_hymn' => $_GET['chief_hymn'] ?? null,
            'canticle' => $_GET['canticle'] ?? 'magnificat',
            'replace_psalm' => isset($_GET['replace_psalm']) && ($_GET['replace_psalm'] === 'on' || $_GET['replace_psalm'] === '1'),
            'override_prayers' => $_GET['override_prayers'] ?? 'default',
            'day_type' => $_GET['day_type'] ?? 'default'
        ];
    }
    
    /**
     * Generate service URL with encoded settings
     * 
     * @param array $settings Service settings array
     * @param string $base_url Base URL (default: service.php)
     * @return string Complete URL
     */
    public static function generateURL($settings, $base_url = 'service.php') {
        $encoded = self::encode($settings);
        return $base_url . '?s=' . $encoded;
    }
    
    /**
     * Validate required settings
     * 
     * @param array $settings Service settings
     * @return array ['valid' => bool, 'missing' => array]
     */
    public static function validateSettings($settings) {
        $required = ['date', 'order_of_service'];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($settings[$field]) || empty($settings[$field])) {
                $missing[] = $field;
            }
        }
        
        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }
}