<?php

use Lutherald\BibleGateway;

class ServiceBuilder
{
    public static function BuildService(
        $date, 
        $order, 
        $canticle, 
        $hymns = [], 
        $replace_with_introit = true, 
        $prayers='default', 
        $section_classes=[])
    {
        $default_section_classes = array(
            "section_class" => "",
            "section_title_class" => "",
            "section_body_class" => ""
        );
        $section_classes = array_merge($default_section_classes, $section_classes);
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        require_once __DIR__ . '/class-lutherald-BibleGateway.php'; 
        $calendar = \Lutherald\ChurchYear::create_church_year($date);
        $day_info = $calendar->retrieve_day_info($date);
        
        // Try to load the order data from different possible locations
        if (file_exists(__DIR__ . '/calendar/ordo/' . $order . '.json')) {
            $order_data = json_decode(file_get_contents(__DIR__ . '/calendar/ordo/' . $order . '.json'));
        } elseif (file_exists(__DIR__ . '/' . $order . '.json')) {
            $order_data = json_decode(file_get_contents(__DIR__ . '/' . $order . '.json'));
        } else {
            die("Could not find the order file: " . $order . ".json");
        }
        
        $hymnal = json_decode(file_get_contents('tlh.json'), true);
        $output = '<h2>' . $day_info['display'] . '</h2>';
        
        foreach ($order_data as $section) {
            $additional_content = '';
            switch ($section->title) {
                case 'Psalmody':
                    if ($replace_with_introit && array_key_exists('introit', $day_info)) {
                        $introit = $day_info['introit'];
                        $additional_content .= "<h3>Introit</h3> <p>$introit</p>";
                        break;
                    }

                    if (!isset($day_info['psalm'][$order])) {
                        break;
                    }
                    
                    $psalm = $day_info['psalm'][$order];
                    if (is_array($psalm)) {
                        foreach($psalm as $p){
                            $additional_content .= "<h4>$p</h4>";
                            $additional_content .= BibleGateway::get_verse($p); 
                        }
                        break; 
                    }
                    $additional_content .= "<h4>$psalm</h4>";
                    $additional_content .= BibleGateway::get_verse($psalm); 
                    break;

                case 'Introit':
                    if (array_key_exists('introit', $day_info)) {
                        $introit = $day_info['introit'];
                        $additional_content .= "<p>$introit</p>";
                    }
                    break;

                case 'Gradual':
                    if (array_key_exists('gradual', $day_info)) {
                        $gradual = $day_info['gradual'];
                        $additional_content .= "<p>$gradual</p>";
                    }
                    break;

                case 'Hymn':
                    if (isset($section->instruction) && strpos($section->instruction, 'Chief') !== false) {
                        // This is the Chief Hymn section
                        $chief_hymn = $hymns[1]; 
                        $hymn_to_load = array_key_exists($chief_hymn, $hymnal) ? $hymnal[$chief_hymn] : '';
                        if ($chief_hymn === 'default') {
                            if (array_key_exists('hymn', $day_info)) {
                                $lectionary_hymn = $day_info['hymn'];
                                // Validate hymn parameter
                                if (array_key_exists('hymnal', $lectionary_hymn) && array_key_exists('index', $lectionary_hymn)) {
                                    // Get the hymnal id 
                                    $hymnal_id = $lectionary_hymn['hymnal'];
                                    $hymn_index = $lectionary_hymn['index'];
                                    
                                    $hymn_to_load = ($hymnal_id === 'TLH') ? 
                                        self::load_tlh_hymn($hymnal["hymn$hymn_index"]) : 
                                        BibleGateway::get_hymn($lectionary_hymn);
                                }
                            } else {
                                $hymn_to_load = '';
                            }
                        } else {
                            $hymn_to_load = self::load_tlh_hymn($hymn_to_load);
                        }
                        $additional_content .= $hymn_to_load;
                    } else {
                        // This is the Opening Hymn section
                        $opening_hymn_data = $hymnal[$hymns[0]];
                        $additional_content .= self::load_tlh_hymn($opening_hymn_data);
                    }
                    break;

                case 'Closing Hymn':
                    // Reuse the Opening Hymn for simplicity, or you could add a third hymn option
                    $opening_hymn_data = $hymnal[$hymns[0]];
                    $additional_content .= self::load_tlh_hymn($opening_hymn_data);
                    break;

                case 'Lection':
                    $readings = $day_info['readings'];
                    foreach($readings as $r){
                        $additional_content .= "<h4>$r</h4>";
                        $additional_content .= '<p>' .BibleGateway::get_verse($r) . '</p>';
                    }
                    break;

                case 'Epistle':
                    if (isset($day_info['readings'][0])) {
                        $epistle = $day_info['readings'][0];
                        $additional_content .= "<h4>$epistle</h4>";
                        $additional_content .= '<p>' . BibleGateway::get_verse($epistle) . '</p>';
                    }
                    break;

                case 'Gospel':
                    if (isset($day_info['readings'][1])) {
                        $gospel = $day_info['readings'][1];
                        $additional_content .= "<h4>$gospel</h4>";
                        $additional_content .= '<p>' . BibleGateway::get_verse($gospel) . '</p>';
                    }
                    break;

                case 'Responsory or Hymn':
                    $chief_hymn = $hymns[1]; 
                    $hymn_to_load = array_key_exists($chief_hymn, $hymnal) ? $hymnal[$chief_hymn] : '';
                    if ($chief_hymn === 'default') {
                        if (array_key_exists('hymn', $day_info)) {
                            $lectionary_hymn = $day_info['hymn'];
                            // Validate hymn parameter
                            if (array_key_exists('hymnal', $lectionary_hymn) && array_key_exists('index', $lectionary_hymn)) {
                                // Get the hymnal id 
                                $hymnal_id = $lectionary_hymn['hymnal'];
                                $hymn_index = $lectionary_hymn['index'];
                                
                                $hymn_to_load = ($hymnal_id === 'TLH') ? 
                                    self::load_tlh_hymn($hymnal["hymn$hymn_index"]) : 
                                    BibleGateway::get_hymn($lectionary_hymn);
                            }
                        } else {
                            $hymn_to_load = '';
                        }
                    } else {
                        $hymn_to_load = self::load_tlh_hymn($hymn_to_load);
                    }
                    $additional_content .= $hymn_to_load;  
                    break;  

                case 'Collect for the Day':
                    if (array_key_exists('collect', $day_info)) {
                        $additional_content .= '<h4>Collect</h4><p>' . $day_info['collect'] . '</p>';
                    }
                    break; 

                case 'Prayers':
                    if ($prayers !== 'default') {
                        $output .= self::render_order_section($section);
                        $prayer_book = json_decode(file_get_contents(__DIR__ . '/calendar/ordo/prayers.json'), true);
                        $section_to_override = $prayer_book[$prayers]; 
                        $output .= '<h3>The ' . $section_to_override['title'] . '</h3>';
                        $output .= $section_to_override['content']; 
                        if ($prayers === 'suffrages') {
                            $output .= $section_to_override[$order]; 
                            $output .= $section_to_override['end'];
                        }
                        
                        break 2; 
                    }
                    break; 

                case 'Canticle':
                    if ($order !== 'chief_service') {
                        $canticles_data = json_decode(file_get_contents(__DIR__ . '/calendar/ordo/canticle.json'), true);
                        $canticle_to_load = $canticles_data[$canticle]; 
                        $additional_content .= '<h4>The '. $canticle_to_load['title'] . '</h4>';
                        if (key_exists('audio', $canticle_to_load)) {
                            $audio_file = $canticle_to_load['audio'];
                            $additional_content .= "<audio src='/calendar/audio/$audio_file' controls></audio>";
                        }
                        $additional_content .= '<p>' . nl2br($canticle_to_load['content']) . '</p>';
                    }
                    break; 
            } 
            $output .= self::render_order_section($section, $additional_content, $section_classes);
        }
        return $output;
    }

    public static function load_tlh_hymn($hymn_data) {
        if (empty($hymn_data)) {
            return '<p>No hymn data available</p>';
        }
        
        $output = '<h4>' . $hymn_data['title'] . '</h4>';

        $url = isset($hymn_data['audiofile']) ? $hymn_data['audiofile'] : ''; 

        // Check if hymn dir exists
        if (is_dir('hymns') && !empty($url)) {
            $hymn_file = basename($url);
            $url = "/hymns/$hymn_file";
            $output .= '<audio src="'. $url .'" controls></audio>';
        }

        if (isset($hymn_data['lyrics']) && is_array($hymn_data['lyrics'])) {
            foreach ($hymn_data['lyrics'] as $stanza) {
                $output .= '<p>' . nl2br($stanza) . '</p>';
            }
        }
        
        return $output;
    }

    public static function render_order_section($section, $additional_content = '', $section_classes=[]) {
        extract($section_classes); 
        $content = property_exists($section, 'content') ? $section->content : ''; 
        $content = preg_replace('/(℟:)(.*)/', '$1<strong>$2</strong>', $content);
        // $content = preg_replace('/^([^\n]*[^℣:℟:\n][^\n]*)$/m', '<em>$0</em>', $content); // italicize lines without '℣:' or '℟:'
        $instruction = property_exists($section, 'instruction') ? $section->instruction : '';
        ob_start();
?>
        <div class="<?php echo isset($section_class)? $section_class : ''?>">
            <h3 class="<?php echo isset($section_title_class)? $section_title_class : ''?>"><?php echo $section->title ?></h3>
            <div class="<?php echo isset($section_body_class)? $section_body_class : ''?>">
            <?php if (!empty($instruction)): ?>
                <p><em><?php echo nl2br($instruction);  ?></em></p>
            <?php endif; ?>
            
            <?php if (!empty($content)): ?>
                <p><?php echo nl2br($content);  ?></p>
            <?php endif; ?>

            <?php
            echo $additional_content;
            if (property_exists($section, 'audio')) {
                $audio = $section->audio;
                $audio_arr = is_array($audio) ? $audio : [$audio];
                foreach ($audio_arr as $a) {
            ?>
                    <audio src=<?php echo '/calendar/audio/' . $a ?> controls>
                    </audio>
            <?php
                }
            } ?>
            </div>
        </div> <?php
                return ob_get_clean();
            }
        }