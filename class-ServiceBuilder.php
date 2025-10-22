<?php

use Lutherald\BibleGateway;

class ServiceBuilder
{
    /**
     * Build a service with a flexible settings array
     * 
     * @param array $settings All service settings
     *   Required keys:
     *     - date: DateTime object or string
     *     - order_of_service: string (matins, vespers, chief_service)
     *     - opening_hymn: string hymn key
     *     - prayers: string prayer type
     *   Optional keys vary by order - see individual Order classes
     * 
     * @return string Rendered service HTML
     */
    public static function BuildService(array $settings)
    {
        // Validate required settings
        $required = ['date', 'order_of_service'];
        foreach ($required as $key) {
            if (!isset($settings[$key])) {
                throw new \InvalidArgumentException(message: "Missing required setting: $key");
            }
        }
        $settings = self::normalizeConfig($settings); 
        // Parse date
        $date = $settings['date'];
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        // Get order type
        $order_type = $settings['order_of_service'];
        $day_type = $settings['day_type'] ?? 'default';
        
        // Setup section classes
        $default_section_classes = [
            "section_class" => "",
            "section_title_class" => "",
            "section_body_class" => ""
        ];
        $settings['section_classes'] = array_merge(
            $default_section_classes, 
            $settings['section_classes'] ?? []
        );
        
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        require_once __DIR__ . '/class-lutherald-BibleGateway.php'; 
        require_once __DIR__ . '/class-TemplateEngine.php';
        require_once __DIR__ . '/orders/class-ServiceOrder.php';
        require_once __DIR__ . '/orders/class-ServiceOrderFactory.php';
        require_once __DIR__ . '/orders/class-MatinsOrder.php';
        require_once __DIR__ . '/orders/class-VespersOrder.php';
        require_once __DIR__ . '/orders/class-ChiefServiceOrder.php';
        
        // Get church calendar info
        $calendar = \Lutherald\ChurchYear::create_church_year($date);
        $day_info = self::getDayInfo($calendar, $date, $day_type);
        
        // Load hymnal data
        $hymnal = json_decode(file_get_contents(__DIR__ . '/tlh.json'), true);
        
        // Create template engine
        $engine = new TemplateEngine(__DIR__ . '/templates');
        
        // Create the appropriate Order object
        $order = ServiceOrderFactory::create($order_type, $settings, $day_info, $hymnal, $engine);
        
        // Merge defaults with settings
        $defaults = $order->getDefaults();
        $settings = array_merge($defaults, $settings);
        
        // Update order with merged settings
        $order = ServiceOrderFactory::create($order_type, $settings, $day_info, $hymnal, $engine);
        
        // Validate order-specific settings
        $validation = $order->validateSettings();
        if (!$validation['valid']) {
            throw new \InvalidArgumentException(
                "Invalid settings for $order_type: " . implode(', ', $validation['missing'])
            );
        }
        
        // Render and return
        return $order->render();
    }

     private static function normalizeConfig(array $config) {
        $normalized = []; // Map form field names to ServiceBuilder keys
        $keyMap = [
            'override_prayers' => 'prayers',

        ];
        
        foreach ($config as $key => $value) {
            // Use mapped key if it exists, otherwise use original key
            $normalizedKey = $keyMap[$key] ?? $key;
            $normalized[$normalizedKey] = $value;
        }
        
        
        
        return $normalized;
    }
    
    /**
     * Get day info based on day type
     */
    private static function getDayInfo($calendar, $date, $day_type)
    {
        switch ($day_type) {
            case 'feast':
                $day_info = $calendar->get_festival($date);
                if ($day_info && isset($day_info['readings'])) {
                    // Normalize readings array
                    $readings = [];
                    foreach ($day_info['readings'] as $r) {
                        $readings[] = $r;
                    }
                    $day_info['readings'] = $readings;
                }
                // Fallback if no feast found
                if (!$day_info || empty($day_info)) {
                    $day_info = $calendar->retrieve_day_info($date);
                }
                break;
                
            case 'ember':
            case 'default':
            default:
                $day_info = $calendar->retrieve_day_info($date);
                break;
        }
        
        return $day_info;
    }
    
    /**
     * Get defaults for a specific order type
     * 
     * @param string $order_type
     * @return array Default settings
     */
    public static function getDefaultsForOrder($order_type)
    {
        // Create a temporary order object to get defaults
        // We'll need minimal dependencies for this
        require_once __DIR__ . '/orders/class-ServiceOrder.php';
        
        switch ($order_type) {
            case 'matins':
                
                $order = new MatinsOrder([], [], [], null);
                break;
            case 'vespers':
                
                $order = new VespersOrder([], [], [], null);
                break;
            case 'chief_service':
                
                $order = new ChiefServiceOrder([], [], [], null);
                break;
            default:
                return [];
        }
        
        return $order->getDefaults();
    }
}