<?php 

class ServiceBuilder{
    public static function BuildService($date, $order, $canticle, $hymns = []){
        require_once __DIR__ . '/calendar/class-lutherald-ChurchYear.php';
        $calendar = \Lutherald\ChurchYear::create_church_year($date); 
        $day_info = $calendar->retrieve_day_info($date);
        return $day_info;
    }
}   