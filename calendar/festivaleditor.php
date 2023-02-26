<?php 
if (!defined('ABSPATH')) wp_die('Cannot access this file directly.');

$json = file_get_contents(dirname(__FILE__) ."/moveable_feasts.json");
$array = json_decode($json, true);

if(!empty($_POST)){

    //TODO: Process JSON
}

$fields_to_edit = [
    "display",
    "date",
    "rank",
    "chief_hymn",
    "readings" => ["epistle", "gospel"], 
    
];