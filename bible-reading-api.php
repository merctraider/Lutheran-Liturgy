<?php 

if(isset($_GET['lookup'])){
    try{
        require_once dirname(__FILE__) . '/class-lutherald-BibleGateway.php';
        echo \Lutherald\BibleGateway::get_verse($_GET['lookup']); 
    } catch (Exception $e){
        echo $e;
    }
    
} else {
    exit('No Verse Given');
}