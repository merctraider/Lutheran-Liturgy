<?php 
/**
 * Vespers Service Order
 */
class VespersOrder extends ServiceOrder
{
    public function getTemplateName()
    {
        return 'vespers';
    }
    
    public function getDefaults()
    {
        return [
            'canticle' => 'magnificat',
            'replace_psalm' => false,
            'chief_hymn' => 'default',
        ];
    }
    
    public function buildContext()
    {
        return array_merge(
            [
                'day_info' => $this->day_info,
                'order' => 'vespers',
                'canticle' => $this->settings['canticle'],
                'replace_with_introit' => $this->settings['replace_psalm'],
                'prayers_override' => $this->settings['prayers'],
                'collect_of_day' => $this->day_info['collect'] ?? null,
            ],
            $this->getSectionClasses(),
            $this->getHymns(),
            $this->getPsalmody(),
            $this->getReadings()
        );
    }
    
    public function postProcessContext(&$context)
    {
        // Same as Matins
        if (isset($context['canticle'])) {
            $context['canticle'] = $this->engine->render(
                'canticles/' . $context['canticle'], 
                $context
            );
        }
        
        if ($context['prayers_override'] !== 'default') {
            $context['prayers_override'] = $this->engine->render(
                'prayers/' . $context['prayers_override'], 
                $context
            );
        }
    }
}