<?php 
/**
 * Chief Service Order
 */
class ChiefServiceOrder extends ServiceOrder
{
    public function getTemplateName()
    {
        return 'chief_service';
    }
    
    public function getDefaults()
    {
        return [
            'include_communion' => true,
            'proper_preface' => 'default',
            'chief_hymn' => 'default',
        ];
    }
    
    public function buildContext()
    {
        return array_merge(
            [
                'day_info' => $this->day_info,
                'order' => 'chief_service',
                'collect_of_day' => $this->day_info['collect'] ?? null,
                'gradual' => $this->day_info['gradual'] ?? null,
                'proper_preface' => $this->getProperPreface(),
                'introit' => $this->day_info['introit'] ?? null,
            ],
            $this->getSectionClasses(),
            $this->getHymns(),
            $this->getReadings(),
            $this->getOTResponsory()
        );
    }
    
    protected function getHymns()
    {
        return [
            'opening_hymn' => $this->prepareHymn($this->settings['opening_hymn']),
            'chief_hymn' => $this->prepareChiefHymn($this->settings['chief_hymn']),
            'post_prayer_hymn'=>  $this->prepareHymn($this->settings['post_prayer_hymn']) ,
            'communion_hymn'=>  $this->prepareHymn($this->settings['communion_hymn']) ,
            'post_communion_hymn'=>  $this->prepareHymn($this->settings['post_communion_hymn']) ,
            'closing_hymn' => $this->prepareHymn($this->settings['closing_hymn']) ,
        ];
    }

    private function getOTResponsory(){

        $responsive_psalm_settings = $this->settings['responsive_psalm']; 
        if($responsive_psalm_settings !== 'matins' && $responsive_psalm_settings !=='vespers'){
            return []; 
        }

         $day_info = $this->day_info; 
         

         $psalm_ref = $day_info['psalm'][$responsive_psalm_settings] ?? null;
        $responsory_text = $this->getPsalmText($psalm_ref);
       
        return [
            'ot_responsory' => $psalm_ref?? false, 
            'ot_responsory_text' => $responsory_text
        ]; 
    }
    
    private function getProperPreface()
    {
        $override = $this->settings['proper_preface'];
        
        // If override specified and not 'default', use it
        if ($override !== 'default') {
             $json = file_get_contents(filename: dirname(__FILE__) . "/prefaces.json");
             $json = json_decode($json, true); 
            return $json[$override];
        }
       

        return false; 
    }
}
