<?php
namespace Lutherald; 
//if(!defined('ABSPATH')) wp_die('Cannot access this file directly.');
class Season{
    protected $start_date; 
    protected $end_date; 

    protected $day_record = []; 

    public function __construct($start_date, $next_season_start){
        $this->start_date = $start_date->format('Y-m-d');
        $next_season = clone $next_season_start; 
        $this->end_date = $next_season->modify('-1 day')->format('Y-m-d');
    }

    public function in_season(\DateTime $date) {
        $start_date = new \DateTime ($this->start_date); 
        $end_date = new \DateTime ($this->end_date);
        return $date >= $start_date && $date <= $end_date;
    }

    //Key will be Y-m-d of the date
    //Display 
    //Readings
    public function register_day($date, $args){
        $this->day_record[$date] = $args;
    }

    public function get_day($date){
        if(array_key_exists($date, $this->day_record)){
            return $this->day_record[$date];
        }
        return false; 
    }

    

}