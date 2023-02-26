<?php
// Retrieve the form inputs and store them as variables
$date = new \Datetime($_POST['date']);
$order_of_service = $_POST['order_of_service'];
$opening_hymn = $_POST['opening_hymn'];
$chief_hymn = $_POST['chief_hymn'];
$canticle = $_POST['canticle'];

require_once 'class-ServiceBuilder.php'; 
$service = ServiceBuilder::BuildService($date, $order_of_service, $canticle, [$opening_hymn, $chief_hymn]);
var_dump($service);