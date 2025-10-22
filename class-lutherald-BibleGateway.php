<?php
namespace Lutherald;

class BibleGateway{

    public static $version = 'NKJV'; 
    public static $apocrypha_version = 'DRA';
    public static $apocryphal_books = ['Ecclesiasticus', 'Tobit', 'Judith', 'Baruch', 'Wisdom', 'Maccabees'];
    

    public static function fetch_url($url){
        //Initialise the curl
        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    $output = curl_exec($ch);
        \curl_close($ch);
        return $output;
    }

    public static function get_verse($lookup){
        if($lookup == null) return null; 
        $output = '';
        //Check if book belongs to the apocrypha
        if(self::is_apocrypha($lookup)){
            $ver = self::$apocrypha_version;
            $output = '<strong>('. self::$apocrypha_version . ') </strong>';
        } else {
            $ver = self::$version;
        }
        
        $lookup = \urlencode($lookup);
        $psalm = false; 

        if(strpos($lookup, 'Psalm') !== false){
            $psalm = true; 
        }

        
        $url = "http://www.biblegateway.com/passage/?search=$lookup&version=$ver";
        require_once  dirname(__FILE__) .'/simple_html_dom.php';
        $content = file_get_html($url);
        
        $passage_html = $content->find('div.passage-text', 0);
        foreach($passage_html->find('p') as $verse){
            foreach($verse->find('sup') as $footnote){
                $footnote->innertext = '';
            }

            if($psalm){
                $output .= $verse;
            } else {
                $output .= $verse->plaintext;
            }
            
        }
        return $output;
        
        return false;      
        
    }
    
    public static function is_apocrypha($lookup){
        
        foreach(self::$apocryphal_books as $book){

            if(\strpos($lookup, $book) !== false){
                return true; 
            }
        }
        

        return false; 
    }

    public static function get_devotions($date){
        $timestamp = $date->format('Y-m-d');
        $url = 'http://eldona.org/wp-json/wp/v2/posts?before='. $timestamp . 'T23:59:00';
        $devotions_json = file_get_contents($url);
        
        if($devotions_json == null || $devotions_json === ''){
            return false; 
        }
        
        $devotions_arr = json_decode($devotions_json, true);
        foreach ($devotions_arr as $entry){
            $entry_date = strtotime($entry['date']);
            $entry_date = date('Y-m-d', $entry_date); 

            if($timestamp == $entry_date){
                $content = $entry['content']['rendered'];
                //Find the devotional content
                $content_array = explode("<strong>Devotion</strong>", $content);
                $devotion = $content_array[1];
                //Strip the first spacing
                $html = str_get_html($devotion);
                $html->find('div._1mf span', 0)->innertext = '';

                $output = '';

                foreach($html->find('div._1mf span') as $paragraph){
                    $p = $paragraph->plaintext;
                    if(strlen($p) > 6)
                    {
                        $output .= "<p>$p</p>";
                    }
                   
                }

                return $output;
            }
        }
        return false;
        
    }

    public static function get_hymn($hymn){

        $output = ''; 
        //Hymnal information
        $hymnal_json = file_get_contents(dirname(__FILE__) ."/calendar/hymnals.json");
        $hymnal_directory = \json_decode($hymnal_json, true); 
        
        //Validate hymn parameter
        if(array_key_exists('hymnal', $hymn) && array_key_exists('index', $hymn)){
            
            //Get the hymnal id 
            $hymnal_id = $hymn['hymnal'];

            //Validate hymn index
            if(array_key_exists($hymnal_id, $hymnal_directory)){
                $hymn_index = $hymn['index'];

                $hymnal = $hymnal_directory[$hymnal_id];

                $output .= $hymnal['display'] . " #$hymn_index";

                //Place hymnary.org link on the text
                $href_value = 'https://hymnary.org/hymn/' . $hymnal['hymnary_code'] . '/' . $hymn_index;

                $output = "<a href='$href_value' target='_blank'>$output</a>";

            }
        }

        return $output;

    }
}