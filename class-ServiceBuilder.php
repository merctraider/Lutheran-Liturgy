<?php

use Lutherald\BibleGateway;

class ServiceBuilder
{
    /**
     * Build a service using array-based configuration
     * 
     * @param array $config Service configuration array with keys:
     *   - date: DateTime|string (required)
     *   - order OR order_of_service: string (required) - Order of service type
     *   - canticle: string (optional, default: 'magnificat')
     *   - hymns OR opening_hymn/chief_hymn: array or individual hymns
     *   - replace_with_introit OR replace_psalm: bool (optional, default: true)
     *   - prayers OR override_prayers: string (optional, default: 'default')
     *   - section_classes: array (optional) - CSS classes for sections
     *   - day_type: string (optional, default: 'default') - Day type: 'default', 'feast', or 'ember'
     * 
     * @return string Rendered service HTML
     */
    public static function BuildService(array $config)
    {
        // Normalize settings keys (support both form field names and ServiceBuilder names)
        $config = self::normalizeConfig($config);
      
        
        // Extract and validate required parameters
        if (!isset($config['date']) || !isset($config['order'])) {
            throw new \InvalidArgumentException('Missing required parameters: date and order');
        }
        
        // Set defaults for optional parameters
        $defaults = [
            'canticle' => 'magnificat',
            'hymns' => [],
            'replace_with_introit' => true,
            'prayers' => 'default',
            'section_classes' => [],
            'day_type' => 'default'
        ];
        
        // Merge with defaults
        $config = array_merge($defaults, $config);
        
        // Process date
        $date = $config['date'];
        if (is_string($date)) {
            $date = new \DateTime($date);
        }
        
        // Process section classes
        $default_section_classes = [
            "section_class" => "",
            "section_title_class" => "",
            "section_body_class" => ""
        ];
        $section_classes = array_merge($default_section_classes, $config['section_classes']);
        
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        require_once __DIR__ . '/class-lutherald-BibleGateway.php'; 
        require_once __DIR__ . '/class-TemplateEngine.php';
        
        // Get church calendar info
        $calendar = \Lutherald\ChurchYear::create_church_year($date);

        // Get day info based on day_type
        if ($config['day_type'] === 'feast') {
            // Get feast day information
            $day_info = $calendar->get_festival($date);
            $readings = []; 
            foreach($day_info['readings'] as $r){
                $readings[] = $r; 
            }
            $day_info['readings'] = $readings; 
            // If no feast found, fall back to default
            if ($day_info === false || empty($day_info)) {
                $day_info = $calendar->retrieve_day_info($date);
            }
        } else {
            // Default or ember day (both use retrieve_day_info)
            $day_info = $calendar->retrieve_day_info($date);
        }
        
        // Load hymnal data
        $hymnal = json_decode(file_get_contents(__DIR__ . '/tlh.json'), true);
        
        // Prepare context data for template
        $context = [
            'day_info' => $day_info,
            'order' => $config['order'],
            'canticle' => $config['canticle'],
            'replace_with_introit' => $config['replace_with_introit'],
            'prayers_override' => $config['prayers'],
            
            // Section CSS classes
            'section_class' => $section_classes['section_class'],
            'section_title_class' => $section_classes['section_title_class'],
            'section_body_class' => $section_classes['section_body_class'],
            
            // Hymns
            'opening_hymn' => self::prepareHymn($config['opening_hymn'] ?? null, $hymnal),
            'chief_hymn' => self::prepareChiefHymn($config['chief_hymn'] ?? null, $day_info, $hymnal),
            'closing_hymn' => self::prepareHymn($config['closing_hymn']?? null, $hymnal),
            
            // Psalmody
            'introit' => isset($day_info['introit']) ? $day_info['introit'] : null,
            'psalm' => self::getPsalmReference($day_info, $config['order']),
            'psalm_text' => self::getPsalmText($day_info, $config['order']),
            
            // Lections
            'ot' => isset($day_info['readings'][2]) ? $day_info['readings'][2] : null,
            'ot_text' => isset($day_info['readings'][2])? self::getReadingText($day_info['readings'][2]) : null,

            'epistle' => isset($day_info['readings'][0]) ? $day_info['readings'][0] : null,
            'epistle_text' => isset($day_info['readings'][0])?  self::getReadingText($day_info['readings'][0]): null,

            'gospel' => isset($day_info['readings'][1]) ? $day_info['readings'][1] : null,
            'gospel_text' => isset($day_info['readings'][1])? self::getReadingText($day_info['readings'][1] ) : null,
            
            // Collects
            'collect_of_day' => isset($day_info['collect']) ? $day_info['collect'] : null,
            
            // Gradual (for Chief Service)
            'gradual' => isset($day_info['gradual']) ? $day_info['gradual'] : null,
        ];
      
        
        // Render the template
        $engine = new TemplateEngine(__DIR__ . '/templates');

        //Build the canticle and prayers
        $context['canticle'] = $engine->render('canticles/' . $context['canticle'], $context);
        if ($context['prayers_override'] !== 'default') {
            $context['prayers_override'] = $engine->render('prayers/' . $context['prayers_override'], $context);
        }
        
        return $engine->render($config['order'], $context);
    }

    /**
     * Normalize configuration keys from form field names to ServiceBuilder names
     * This allows passing the settings array directly without manual mapping
     * Dynamically builds arrays based on field types from ordo_config.json
     */
    private static function normalizeConfig(array $config) {
        $normalized = [];
        
        // Map form field names to ServiceBuilder keys
        $keyMap = [
            'order_of_service' => 'order',
            'override_prayers' => 'prayers',
            'replace_psalm' => 'replace_with_introit'
        ];
        
        foreach ($config as $key => $value) {
            // Use mapped key if it exists, otherwise use original key
            $normalizedKey = $keyMap[$key] ?? $key;
            $normalized[$normalizedKey] = $value;
        }
        
        
        
        return $normalized;
    }
    
    /**
     * Load ordo configuration from JSON file
     */
    private static function loadOrdoConfig() {
        static $config = null;
        
        if ($config === null) {
            $json = file_get_contents(__DIR__ . '/calendar/ordo_config.json');
            $config = json_decode($json, true);
        }
        
        return $config;
    }

    /**
     * LEGACY METHOD - For backward compatibility
     * Build a service using individual parameters (deprecated)
     * 
     * @deprecated Use BuildService(array $config) instead
     */
    public static function BuildServiceLegacy(
        $date, 
        $order, 
        $canticle, 
        $hymns = [], 
        $replace_with_introit = true, 
        $prayers = 'default', 
        $section_classes = [],
        $day_type = 'default'  
    ) {
        // Convert to array format and call new method
        return self::BuildService([
            'date' => $date,
            'order' => $order,
            'canticle' => $canticle,
            'hymns' => $hymns,
            'replace_with_introit' => $replace_with_introit,
            'prayers' => $prayers,
            'section_classes' => $section_classes,
            'day_type' => $day_type
        ]);
    }
    
    /**
     * Prepare hymn HTML
     */
    private static function prepareHymn($hymn_number, $hymnal)
    {
        if (empty($hymn_number) || !isset($hymnal[$hymn_number])) {
            return null;
        }
        
        return self::load_tlh_hymn($hymnal[$hymn_number]);
    }
    
    /**
     * Prepare chief hymn (with lectionary fallback)
     */
    private static function prepareChiefHymn($hymn_number, $day_info, $hymnal)
    {
        // If "default", use lectionary hymn
        if ($hymn_number === 'default' && isset($day_info['hymn'])) {
            $lect_hymn = $day_info['hymn'];
            if (is_array($lect_hymn)) {
                if($lect_hymn['hymnal'] !== 'TLH'){
                    return $lect_hymn['hymnal'] . ' ' . $lect_hymn['index']; 
                } else {
                    $hymn_number = "hymn" . $lect_hymn['index'];
                    
                }
            } else {
                $hymn_number = $day_info['hymn'];
            }
        }


        if (empty($hymn_number) || !isset($hymnal[$hymn_number])) {
            return null;
        }
        
        return self::load_tlh_hymn($hymnal[$hymn_number]);
    }
    
    /**
     * Get psalm reference for display
     */
    private static function getPsalmReference($day_info, $order)
    {
        if (!isset($day_info['psalm'][$order])) {
            return null;
        }
        
        $psalm = $day_info['psalm'][$order];
        
        if (is_array($psalm)) {
            return implode(', ', $psalm);
        }
        
        return $psalm;
    }
    
    /**
     * Get psalm text from BibleGateway
     */
    private static function getPsalmText($day_info, $order)
    {
        if (!isset($day_info['psalm'][$order])) {
            return null;
        }
        
        $psalm = $day_info['psalm'][$order];
        $output = '';
        
        if (is_array($psalm)) {
            foreach ($psalm as $p) {
                $output .= '<h4>' . htmlspecialchars($p) . '</h4>';
                $output .= BibleGateway::get_verse($p);
            }
        } else {
            $output .= BibleGateway::get_verse($psalm);
        }
        
        return $output;
    }

    private static function getReadingText($reading){
        if(!isset($reading)){
            return null; 
        }
        return BibleGateway::get_verse($reading);
    }
    
    /**
     * Load hymn from TLH hymnal (keeps existing functionality)
     */
    public static function load_tlh_hymn($hymn_data)
    {
        if (empty($hymn_data)) {
            return '<p>No hymn data available</p>';
        }
        
        $output = '<h4>' . htmlspecialchars($hymn_data['title']) . '</h4>';

        $url = isset($hymn_data['audiofile']) ? $hymn_data['audiofile'] : ''; 

        // Check if hymn dir exists
        if (is_dir(__DIR__ . '/hymns') && !empty($url)) {
            $hymn_file = basename($url);
            $url = "/hymns/$hymn_file";
            $output .= '<audio src="' . htmlspecialchars($url) . '" controls></audio>';
        }

        if (isset($hymn_data['lyrics']) && is_array($hymn_data['lyrics'])) {
            foreach ($hymn_data['lyrics'] as $stanza) {
                $output .= '<p>' . nl2br(htmlspecialchars($stanza)) . '</p>';
            }
        }
        
        return $output;
    }
}