<?php

namespace Lutherald;
//if(!defined('ABSPATH')) wp_die('Cannot access this file directly.');
class ChurchYear{
    protected $year; 
    private $seasons = []; 

    private $easter; 

    public static function create_church_year($current_date){
        $current_date->setTime(0,0,0);
        //Create variables to work with
        if(\is_string($current_date)){
            $timestamp = strtotime($current_date . '-01');
            $year = date('Y', $timestamp);
        } else {
            $year = $current_date->format('Y');
        }

        
        $last_year = new ChurchYear($year - 1);
        $this_year = new ChurchYear($year);
        $calendar_to_use = null;

        

        if ($last_year->find_season($current_date) != false) {
            $calendar_to_use = $last_year;
        }

        if ($this_year->find_season($current_date) != false) {
            $calendar_to_use = $this_year;
        }

       
        return $calendar_to_use;
    }

    public function __construct($year){
        $this->year = $year; 
        $this->easter = $this->get_easter_datetime($this->year+1);
        $this->set_up_seasons();
        $this->load_ember_days();
    }


    function set_up_seasons(){
        require_once 'class-lutherald-Season.php';        

        $seasons = [];
        $first_advent = $this->get_advent_sunday(1);
        $christmas = $this->get_christmas();
        $epiphany = $this->get_epiphany();
        $septuagesima = $this->get_gesima('septuagesima');
        $ash_wednesday = $this->get_lent_start();
        $easter = $this->get_easter();
        $pentecost = $this->get_pentecost();
        $last_sunday = $this->get_last_sunday();       
        $next_year = clone $last_sunday;
        $next_year->modify('next Sunday');
        
        $seasons['advent'] = new Season($first_advent, $christmas);
        $seasons['christmas'] = new Season($christmas, $epiphany);
        $seasons['epiphany'] = new Season($epiphany, $septuagesima);
        $seasons['lententide'] = new Season($septuagesima, $easter); 
        $seasons['easter'] = new Season($easter, $pentecost);
        $seasons['ordinary_time'] = new Season($pentecost, $next_year);


        $this->seasons = $seasons;
        
        $this->load_moveable_feasts();
    }

    public function load_moveable_feasts(){
        
        $json = file_get_contents(dirname(__FILE__) ."/moveable_feasts.json");

        $seasons_records = json_decode($json, true);

        foreach($seasons_records as $church_season => $weeks){
            $season_to_modify = $this->seasons[$church_season]; 
            foreach($weeks as $week){
                $callback = $week['callback'];
                //Get the date of the Sunday 
                if(array_key_exists('value', $callback)){
                    $date = call_user_func(array($this, $callback['function']), $callback['value']);
                } else {
                    $date = call_user_func(array($this, $callback['function']));
                }

                if(!$date){
                    continue; 
                }
                $date_display = $date->format('Y-m-d');

                
                $sunday_entry = [];
                //Readings and liturgical color for the sunday
                $sunday_entry['display'] = $week['display'];
                $sunday_entry['readings'][0] = $week['sunday_readings']['epistle']; 
                $sunday_entry['readings'][1] = $week['sunday_readings']['gospel']; 
                
                //OT Reading
                if(\key_exists('OT', $week['sunday_readings'])){
                    $sunday_entry['readings'][2] = $week['sunday_readings']['OT'];
                }

                $sunday_entry['color'] = $week['color'];
                
                //Array parameters to add 
                $array_parameters = ['psalm', 'hymn'];
                foreach($array_parameters as $array_param){
                    if(\key_exists($array_param, $week)){
                        $sunday_entry[$array_param] = $week[$array_param];
                    }
                } 
                
                //Other stuff to look out for
                $additional_params = ['introit', 'collect', 'gradual'];

                //Check if there is an entry for the additional parameters and include it 
                foreach($additional_params as $a){
                    if(\strlen($week[$a]) > 5){
                        $sunday_entry[$a] = $week[$a];
                    }
                }               

                $season_to_modify->register_day( $date_display, $sunday_entry);

                //Register the weekdays
                if(array_key_exists('weekday_readings', $week)){
                    $weekdays = $week['weekday_readings'];
                    $start_date = clone $date; 

                    foreach($weekdays as $key => $weekday){
                        $weekday_date = $start_date->modify('+1 days'); 
                        $weekday_date = $weekday_date->format('Y-m-d');

                        $weekday_name = date('l',strtotime($weekday_date));
                        $weekday_entry = [];

                        $weekday_entry['display'] = \str_replace('WEEKDAY', $weekday_name, $week['weekday_display']);
                        $weekday_entry['readings'] = $weekday;
                        $weekday_entry['color'] = $week['color'];

                        //Weekly Psalm
                        if(key_exists('psalm', $week)){
                            $weekday_entry['psalm'] = $week['psalm'];
                        }
                       

                        //If the collect is aite, include it
                        if(\strlen($week['collect']) > 5){
                            $weekday_entry['collect'] = $week['collect'];
                        }

                        $season_to_modify->register_day($weekday_date, $weekday_entry);
                    }

                }


                
                
            }
        }
    }

    public function load_ember_days(){
        require_once 'class-lutherald.EmberDays.php';
        $ember_days = new EmberDays($this, $this->year);
        $ember_days->write_ember_days($this->seasons);

    }

    public function retrieve_day_info($date){
        if(\is_string($date)){
            $date = new \DateTime($date);
        }
        $seasons = $this->seasons;
        $season_of_day = $this->find_season($date);
        if(!$season_of_day){
            return false; 
        }
        return $seasons[$season_of_day]->get_day($date->format('Y-m-d'));
    }

    /**
     * Get the Psalm appointed for the day from the 31 day psalter
     * @param DateTime $date The date. 
     * @return array
     *
    */
    public function get_monthly_psalter($date){
        if(\is_string($date)){
            $date = new \DateTime($date);
        }
        $day = $date->format('d');

        //Open the json file
        $json = file_get_contents(dirname(__FILE__) ."/monthly_psalter.json");
        $psalter = json_decode($json, true);

        if(\is_string($day)){
            $day = (int)$day;
        }
        return $psalter[$day];
    }

    /**
     * Check if there is a feast
     */
    public function get_festival($date){
        if(\is_string($date)){
            $date = new \DateTime($date); 
        }
        $monthdate = $date->format('n-j'); 

        
        //Open the json file 
        $json = file_get_contents(dirname(__FILE__) ."/specialfeasts.json");
        $feasts = json_decode($json, true);
        
        foreach($feasts as $feast){
            if($feast[ 'date' ] == $monthdate){
                return $feast;
            }
        }
        return false; 
    }
    

    public function find_season($date){
        if(\is_string($date)){
            $date = new \DateTime($date);
        }
        $seasons = $this->seasons;
        foreach($seasons as $season_id => $season){
            if($season->in_season($date)){
                return $season_id;
            }
        }
        return false; 
    }

    function name_week($index,$preposition ,$feast_name){
        $number_formatter = new \NumberFormatter('en_US', \NumberFormatter::ORDINAL);
        return $number_formatter->format($index) . 'week '.$preposition.' '.$feast_name;
    }


    protected static function get_easter_datetime($year) {
        $base = new \DateTime("$year-03-21");
        $days = easter_days($year);
    
        return $base->add(new \DateInterval("P{$days}D"));
    }

    public function get_lent_start(){
        $easter = $this->get_easter();

        return date_sub($easter,date_interval_create_from_date_string("46 days"));
    }

    public function get_easter(){
        return clone $this->easter;
    }

    public function get_goodfriday(){
        return $this->get_easter()->modify('-2 days');
    }

    public function get_maundy_thursday(){
        return $this->get_goodfriday()->modify('-1 days');
    }

    public function get_christmas($offset=null){
        $date = date_create($this->year.'-12-25');
        if($offset != null){
            $date->modify($offset);
        }
        return $date;
    }

    //Will return false if it falls on a Sunday
    public function get_christmas_day($offset){
        $date = $this->get_christmas($offset);
        if($date->format('l') === 'Sunday'){
            return false; 
        }
        return $date; 
    }

    public function get_advent_sunday($week){
        //Christmas is the starting point
        $advent_date = $this->get_christmas();

        //There are only 4 Sundays in advent!
        if($week > 4){
            return null; 
        }

        //How many last Sundays we have to go
        //e.g. Advent 4 just takes 1 last Sunday, Advent 1 takes 4 Sundays
        $iterations = 5-$week; 
        for($i=0; $i<$iterations; $i++ ){
            $advent_date->modify('last Sunday');
        }
        return $advent_date;
    }

    public function get_advent_sundays(){
        $sundays =[];
        for($i =1; $i<=4;$i++){
            $sundays[$i] = $this->get_advent_sunday($i);
        }
        return $sundays;
    }

    public function get_epiphany(){
        return date_create(($this->year+1).'-1-6');
    }

    public function get_epiphany_sundays(){
        $epiphany = $this->get_epiphany();
        $transfig = $this->get_transfiguration();

        $sunday_count = floor($epiphany->diff($transfig)->days/7);

        $sundays = [$this->get_epiphany()->modify('next Sunday')];
        for ($i=1; $i<$sunday_count; $i++){
            $sundays[$i] = $this->get_epiphany()->modify('+'.$i.' weeks Sunday');
        }
        return $sundays;
    }

    //Check if the set of readings for an Epiphany sunday exists. If not, return false
    public function check_epiphany_sunday($sunday){
        $epiphany_sundays = $this->get_epiphany_sundays();
        $index_to_query = $sunday -1;

        if(array_key_exists($index_to_query, $epiphany_sundays)){
            return $epiphany_sundays[$index_to_query];
        }

        return false; 
    }

    public function get_transfiguration(){
        $date = $this->get_gesima('septuagesima');
        return $date->modify('last Sunday');
    }

    public function get_gesima($sunday){
        $days = '-3 ';         
        $date = $this->get_lent_start(); 

        switch($sunday){
            case 'quinquagesima':
                $days = '-3 '; 
                break; 

            case 'sexagesima':
                $days = '-10 '; 
                break;

            case 'septuagesima':
                $days = '-17 '; 
                break; 

        } 

        return $date->modify($days . 'days');
    }

    public function get_lent($sunday){
        $date = $this->get_lent_start();
        if($sunday == 1){
            $date->modify('next Sunday');
        } else {
            $weeks = $sunday-1; 
            $date->modify('+'.$weeks . ' weeks sunday');
        }
        return $date; 
    }

    public function get_eastertide($sunday){
        $date = $this->get_easter();
        $date->modify('+'.$sunday . ' weeks sunday');

        return $date;
    }

    public function get_pentecost(){
        return $this->get_eastertide(7);
    }

    public function get_ascension(){
        return $this->get_pentecost()->modify('-10 days');
    }

    public function get_trinity_sunday(){
        $pentecost = $this->get_pentecost();
        return $pentecost->modify('next Sunday');
    }

    public function get_trinity_sundays(){
        $trinity = $this->get_trinity_sunday();
        $final_sunday = $this->get_last_sunday();

        $sunday_count = floor(($trinity->diff($final_sunday)->days)/7);
        $sundays = [];
        for ($i=0; $i<$sunday_count; $i++){
            $offset = $i+1;
            $sundays[$i] = $this->get_trinity_sunday()->modify('+'.$offset.' weeks Sunday');
        }
        return $sundays;

    }

    //Check if the set of readings for an Epiphany sunday exists. If not, return false
    public function check_ordinary_time($sunday){
        $trinity_sundays = $this->get_trinity_sundays();
        $index_to_query = $sunday -1;
        if(array_key_exists($index_to_query, $trinity_sundays)){
            return $trinity_sundays[$index_to_query];
        }

        return false; 
    }


    public function get_last_sunday($offset=null){
        $date= new \DateTime($this->year + 1 . '-12-25'); //Next year
        $date->modify('-5 weeks Sunday');

        if($offset != null){
            $date->modify($offset);
        }
        return $date;
    }

  

   

}