<?php 
if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

$json = file_get_contents(dirname(__FILE__) ."/moveable_feasts.json");
$array = json_decode($json, true);
if(!empty($_GET)){
    //Get into each season's weeks array
    foreach($_GET as $season=>$weeks){
        //Get into the week array
        foreach($weeks as $week_id=>$week_info){
            foreach($week_info as $key => $value){
                $array[$season][$week_id][$key] = $value;
            }
        }
    }

    $new_json = json_encode($array);
    $json_file_handler = fopen(dirname(__FILE__) ."/moveable_feasts.json", 'w');
    fwrite($json_file_handler, $new_json);
    fclose($json_file_handler);
    var_dump($array);
    exit();
} 



$season_names = []; 
foreach ($array as $season_name => $weeks){
    $season_names[] = $season_name;
}

$season_select_field = new \Field('seasons', 'Season', $season_names[0]);
$season_select_field->draw_dropdown($season_names);

$fields_to_edit = [
    'collect',
    'introit',
    'gradual'
];
?> 



<form method="post">
    <?php 
        foreach($array as $season_name => $weeks){
            echo '<div class="season_section" id="'. $season_name .'">';
            echo '<h2>' . $season_name . '</h2>';
            foreach($weeks as $i=>$week){
                echo '<div class="week" id="'. $season_name. '-' .$week['display'] .'">';
                echo '<h3>' . $week['display'] . '</h3>';

                echo '<table class="form-table">';
                echo '<tbody>';
                
                foreach($fields_to_edit as $field_name){
                    echo '<tr>';
                    echo '<td>';
                    if(array_key_exists($field_name,$week)){
                        $value = $week[$field_name];
                    } else {
                        $value = '';
                    }
                    
                    $name = $season_name . "[$i][$field_name]";
                    $field = new \Field($name, $field_name, $value);
                    $field->draw_text_field(); 
                    echo '</td>';
                    echo '</tr>';
                }                
                echo '</tbody>';
                echo '</table>';
                echo '</div>';

            }
            echo '</div>';
        }
    ?>
<input type="hidden">
<input type="submit">
</form>

<script>
    jQuery(document).ready(
        function(){
            
            hideall();
            jQuery('select[name="seasons"]').change(function(e){
                hideall();
                var season = jQuery('select[name="seasons"] option:selected').html();
                console.log(season);
                jQuery('#'+ season).show();
            });
        }
    );

    function hideall(){
        <?php 
            foreach($season_names as $s){
                echo 'jQuery("#'. $s . '").hide();';
            }    
                
            ?>
    }
</script>