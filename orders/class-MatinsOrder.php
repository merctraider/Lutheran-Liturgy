<?php 
/**
 * Matins Service Order
 */
class MatinsOrder extends ServiceOrder
{
    public function getTemplateName()
    {
        return 'matins';
    }
    
    public function getDefaults()
    {
        return [
            'canticle' => 'te_deum',
            'replace_psalm' => false,
            'chief_hymn' => 'default',
        ];
    }
    
    public function buildContext()
    {
        return array_merge(
            [
                'day_info' => $this->day_info,
                'order' => 'matins',
                'canticle' => $this->settings['canticle'],
                'replace_with_introit' => $this->settings['replace_psalm'],
                'prayers_override' => $this->settings['prayers'],
                'collect_of_day' => $this->day_info['collect'] ?? null,
                'additional_collects' => $this->getAdditionalCollects(),
            ],
            $this->getSectionClasses(),
            $this->getHymns(),
            $this->getPsalmody(),
            $this->getReadings()
        );
    }
    
    public function postProcessContext(&$context)
    {
        // Render canticle sub-template
        if (isset($context['canticle'])) {
            $context['canticle'] = $this->engine->render(
                'canticles/' . $context['canticle'], 
                $context
            );
        }
        
        // Render prayers if not default
        if ($context['prayers_override'] !== 'default') {
            $context['prayers_override'] = $this->engine->render(
                'prayers/' . $context['prayers_override'], 
                $context
            );
        }
    }
}