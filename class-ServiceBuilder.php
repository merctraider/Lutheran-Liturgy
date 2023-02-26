<?php 

class ServiceBuilder{
    public static function BuildService($date, $order, $canticle, $hymns = [], $replace_with_introit = true){
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        $calendar = \Lutherald\ChurchYear::create_church_year($date); 
        $day_info = $calendar->retrieve_day_info($date);   

        $order_data =  json_decode(file_get_contents(__DIR__ . '/calendar/ordo/' . $order . '.json'));
        
        $output = '';
        foreach($order_data as $section){
            $output .= self::render_order_section($section); 
        }
        return $output; 

    }

    public static function render_order_section($section){
        ob_start(); 
        ?> 
        <div>
        <h3>The <?php echo $section->title ?></h3>
        <p><?php echo  nl2br($section->content);  ?></p>
        
        <?php
        if(property_exists($section, 'audio')){
            $audio = $section->audio; 
            $audio_arr = is_array($audio) ? $audio : [$audio]; 
            foreach($audio_arr as $a){
                ?>
                <audio src=<?php echo '/calendar/audio/' . $a ?> controls>
                    
                </audio>
                <?php
            }
        }?> 
        </div> <?php 
        return ob_get_clean();
    }



}   