<?php 

class ServiceBuilder{
    public static function BuildService($date, $order, $canticle, $hymns = [], $replace_with_introit = true){
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        $calendar = \Lutherald\ChurchYear::create_church_year($date); 
        $day_info = $calendar->retrieve_day_info($date);   
        var_dump($day_info);
        $order_data =  json_decode(file_get_contents(__DIR__ . '/calendar/ordo/' . $order . '.json'));
        $hymnal = json_decode(file_get_contents('tlh.json'), true);
        $output = '';
        foreach($order_data as $section){
            $additional_content = '';
            switch($section->title){
                case 'Psalmody':
                    if($replace_with_introit && array_key_exists('introit', $day_info)){
                        $introit = $day_info['introit'];
                        $additional_content .= "<h3>Introit</h3> <p>$introit</p>";
                        break; 
                    }

                    $psalm = $day_info['psalm'][$order];
                    $additional_content .= "<h4>$psalm</h4>";
                    break; 

                case 'Hymn':
                    $opening_hymn_data = $hymnal[$hymns[0]]; 
                    $additional_content .= '<h4>'. $opening_hymn_data['title'] . '</h4>';
                    foreach($opening_hymn_data['lyrics'] as $stanza){
                        $additional_content .= '<p>' . nl2br($stanza). '</p>';
                    }
                    break; 
            }
            $output .= self::render_order_section($section, $additional_content); 
        }
        return $output; 

    }

    public static function render_order_section($section, $additional_content = ''){
        ob_start(); 
        ?> 
        <div>
        <h3>The <?php echo $section->title ?></h3>
        <p><?php echo  nl2br($section->content);  ?></p>
        
        <?php
        echo $additional_content;
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