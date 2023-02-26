<?php 

namespace Lutherald;
//if(!defined('ABSPATH')) wp_die('Cannot access this file directly.');
class EmberDays{

    private ChurchYear $calendar;    
    private $year;

    public function __construct(ChurchYear $calendar, $year){
        $this->calendar = $calendar; 
        $this->year = $year;
    }




    public function write_ember_days($seasons){
        $json = file_get_contents(dirname(__FILE__) ."/ember_days.json");

        $seasons_records = json_decode($json, true);

        foreach($seasons_records as $season_name => $season_data){
            $season_to_modify = $seasons[$season_name];

            foreach($season_data as $day_of_the_week => $day){
                //Register entry
                $day_entry = []; 

                $day_entry['display'] = 'Ember ' . $day_of_the_week;

                if($season_name === 'easter'){
                    $day_entry['display'] = 'Rogation ' . $day_of_the_week;
                }

                $day_entry['readings'][0] = $day['readings']['epistle'];
                $day_entry['readings'][1] = $day['readings']['gospel'];

                if(array_key_exists('psalm', $day)){
                    $day_entry['psalm'] = $day['psalm'];
                }
                


                //Ember days colour are always violet
                $day_entry['color'] = 'violet';
                
                //Except for pentecost
                if($season_name == 'ordinary_time'){
                    $day_entry['color'] = 'red';
                }

                //Other stuff to look out for
                $additional_params = ['introit', 'collect', 'gradual'];
                 
                //Check if there is an entry for the additional parameters and include it 
                foreach($additional_params as $a){
                    if(\strlen($day[$a]) > 5){
                        $day_entry[$a] = $day[$a];
                    }
                }               

                $season_to_modify->register_day($this->calculate_ember_day($season_name, $day_of_the_week), $day_entry);
            }
        }      

    }

    //Return Datetime of season
    public function calculate_ember_day($season_name, $day_of_the_week){
        $calendar = $this->calendar;
        $date = null; 

        switch($season_name){
            case 'advent':
                //Advent is anchored to St. Lucy's feast day
                $date = \date_create($this->year.'-12-13');
                break; 
            case 'lententide':
                //Anchored to invocavit
                $date = $calendar->get_lent(1); 
                break;
            case 'easter':
                //Anchored to Rogate
                $date = $calendar->get_eastertide(5);
                break; 
            case 'ordinary_time':
                //Anchored to Pentecost
                $date = $calendar->get_pentecost();                
                break; 
        }

        if($date instanceof \DateTime ){
            $date->modify('next ' . $day_of_the_week);
        } else {
            throw new Exception('Date validation failed');
        }

        return $date->format('Y-m-d');
    }
}