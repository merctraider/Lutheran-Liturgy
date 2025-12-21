<?php

/**
 * Abstract base class for all service orders
 */
abstract class ServiceOrder
{
    protected $settings;
    protected $day_info;
    protected $hymnal;
    protected $engine;
    
    public function __construct($settings, $day_info, $hymnal, $engine)
    {
        $this->settings = $settings;
        $this->day_info = $day_info;
        $this->hymnal = $hymnal;
        $this->engine = $engine;
    }
    
    /**
     * Get the template name for this order
     */
    abstract public function getTemplateName();
    
    /**
     * Build the context array for this order
     */
    abstract public function buildContext();
    
    /**
     * Get default settings for this order
     */
    abstract public function getDefaults();
    
    /**
     * Validate settings specific to this order
     */
    public function validateSettings()
    {
        // Base validation - can be overridden
        $required = [];
        $missing = [];
        
        foreach ($required as $field) {
            if (!isset($this->settings[$field]) || empty($this->settings[$field])) {
                $missing[] = $field;
            }
        }
        
        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }
    
    /**
     * Post-process context (e.g., render sub-templates)
     */
    public function postProcessContext(&$context)
    {
        // Override in child classes if needed
    }
    
    /**
     * Render the complete service
     */
    public function render()
    {
        $context = $this->buildContext();
        $this->postProcessContext($context);
        return $this->engine->render($this->getTemplateName(), $context);
    }
    
    // ========== Helper Methods (shared across all orders) ==========
    
    protected function prepareHymn($hymn_number)
    {
        if (empty($hymn_number) || $hymn_number === 'same' || !isset($this->hymnal[$hymn_number])) {
            return null;
        }
        
        return $this->loadTlhHymn($this->hymnal[$hymn_number]);
    }
    
    protected function prepareChiefHymn($hymn_number)
    {
        if ($hymn_number === 'default' && isset($this->day_info['hymn'])) {
            $lect_hymn = $this->day_info['hymn'];
            if (is_array($lect_hymn)) {
                if ($lect_hymn['hymnal'] !== 'TLH') {
                    return $lect_hymn['hymnal'] . ' ' . $lect_hymn['index']; 
                } else {
                    $hymn_number = $lect_hymn['index'];
                }
            } else {
                $hymn_number = $lect_hymn;
            }
        }
        
        return $this->prepareHymn($hymn_number);
    }
    
    protected function getReadingText($reference)
    {
        try {
            $bg = new \Lutherald\BibleGateway();
            return $bg->get_verse($reference);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    protected function loadTlhHymn($hymn_data)
    {
        if (empty($hymn_data)) {
            return '<p>No hymn data available</p>';
        }
        
        $output = '<h4>' . htmlspecialchars($hymn_data['title']) . '</h4>';

        $url = isset($hymn_data['audiofile']) ? $hymn_data['audiofile'] : ''; 

        // Check if hymn dir exists
        if (is_dir(__DIR__ . '/../hymns') && !empty($url)) {
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
    
    protected function getSectionClasses()
    {
        return [
            'section_class' => $this->settings['section_classes']['section_class'],
            'section_title_class' => $this->settings['section_classes']['section_title_class'],
            'section_body_class' => $this->settings['section_classes']['section_body_class'],
        ];
    }
    
    protected function getHymns()
    {
        return [
            'opening_hymn' => $this->prepareHymn($this->settings['opening_hymn']),
            'chief_hymn' => $this->prepareChiefHymn($this->settings['chief_hymn']),
            'closing_hymn' => isset($this->settings['closing_hymn']) 
                ? $this->prepareHymn($this->settings['closing_hymn']) 
                : null,
            
        ];
    }
    
    protected function getReadings()
    {
        $readings = $this->day_info['readings'];
        

        
        return [
            'ot' => $readings[2] ?? null,
            'ot_text' => isset($readings[2]) 
                ? $this->getReadingText($this->day_info['readings'][2]) 
                : null,
            
            'epistle' => $readings[0] ?? null,
            'epistle_text' => isset($readings[0]) 
                ? $this->getReadingText($readings[0]) 
                : null,
            
            'gospel' => $readings[1] ?? null,
            'gospel_text' => isset($readings[1]) 
                ? $this->getReadingText($readings[1]) 
                : null,
        ];
    }
    
    protected function getPsalmody()
    {
        $psalm_ref = $this->day_info['psalm'][$this->settings['order_of_service']] ?? null;

        // Format psalm reference for display (handle both string and array)
        $psalm_display = $psalm_ref;
        if (is_array($psalm_ref)) {
            $psalm_display = implode(', ', $psalm_ref);
        }

        return [
            'introit' => $this->day_info['introit'] ?? null,
            'psalm' => $psalm_display,
            'psalm_text' => $psalm_ref ? $this->getPsalmText($psalm_ref) : null,
        ];
    }
    
    protected function getPsalmText($psalm_ref)
    {
        // Handle array of psalm references (e.g., Christmas Day has multiple psalms)
        if (is_array($psalm_ref)) {
            $combined_output = '';
            foreach ($psalm_ref as $index => $ref) {
                $psalm_text = $this->getReadingText($ref);
                if ($psalm_text) {
                    // Add spacing between multiple psalms
                    if ($index > 0) {
                        $combined_output .= '<br><br>';
                    }
                    // Add heading before each psalm
                    $combined_output .= '<h4>' . htmlspecialchars($ref) . '</h4>';
                    $combined_output .= $psalm_text;
                }
            }
            return $combined_output;
        }

        // Handle single psalm reference (normal case)
        return $this->getReadingText($psalm_ref);
    }

    /**
     * Get additional collects based on selected IDs
     */
    protected function getAdditionalCollects()
    {
        // Check if additional_collects is set and not empty
        if (!isset($this->settings['additional_collects']) || empty($this->settings['additional_collects'])) {
            return [];
        }

        // Load collects.json
        $collects_file = __DIR__ . '/../calendar/collects.json';
        if (!file_exists($collects_file)) {
            return [];
        }

        $collects_json = file_get_contents($collects_file);
        $collects_data = json_decode($collects_json, true);

        if (!isset($collects_data['collects'])) {
            return [];
        }

        // Parse the selected IDs (might be comma-separated string)
        $selected_ids = $this->settings['additional_collects'];
        if (is_string($selected_ids)) {
            $selected_ids = array_map('trim', explode(',', $selected_ids));
        } else if (!is_array($selected_ids)) {
            $selected_ids = [$selected_ids];
        }

        // Filter collects by selected IDs
        $additional_collects = [];
        foreach ($collects_data['collects'] as $collect) {
            if (in_array((string)$collect['id'], $selected_ids)) {
                $additional_collects[] = $collect['text'];
            }
        }

        return $additional_collects;
    }
}