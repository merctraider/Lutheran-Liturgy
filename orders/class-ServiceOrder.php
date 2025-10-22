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
        return [
            'ot' => $this->day_info['readings'][2] ?? null,
            'ot_text' => isset($this->day_info['readings'][2]) 
                ? $this->getReadingText($this->day_info['readings'][2]) 
                : null,
            
            'epistle' => $this->day_info['readings'][0] ?? null,
            'epistle_text' => isset($this->day_info['readings'][0]) 
                ? $this->getReadingText($this->day_info['readings'][0]) 
                : null,
            
            'gospel' => $this->day_info['readings'][1] ?? null,
            'gospel_text' => isset($this->day_info['readings'][1]) 
                ? $this->getReadingText($this->day_info['readings'][1]) 
                : null,
        ];
    }
    
    protected function getPsalmody()
    {
        $psalm_ref = $this->day_info['psalm'][$this->settings['order_of_service']] ?? null;
        
        return [
            'introit' => $this->day_info['introit'] ?? null,
            'psalm' => $psalm_ref,
            'psalm_text' => $psalm_ref ? $this->getPsalmText($psalm_ref) : null,
        ];
    }
    
    protected function getPsalmText($psalm_ref)
    {
         

        return $this->getReadingText($psalm_ref);
    }
}