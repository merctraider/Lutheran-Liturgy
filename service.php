<?php
// Retrieve the form inputs and store them as variables
$date = $_POST['date'];
$order_of_service = $_POST['order_of_service'];
$opening_hymn = $_POST['opening_hymn'];
$chief_hymn = $_POST['chief_hymn'];
$canticle = $_POST['canticle'];
$replace_psalm = isset($_POST['replace_psalm']) && $_POST['replace_psalm'] === 'on';
$title = ucfirst($order_of_service) . ' for ' ; 
require_once 'class-ServiceBuilder.php'; 
$service = ServiceBuilder::BuildService(new \DateTime($date), $order_of_service, $canticle, [$opening_hymn, $chief_hymn], $replace_psalm);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo  $title ?></title>
    <!-- Add Bootstrap stylesheet -->
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
    <?php
    echo '<h1>'. $title . '</h1>';
    
    echo $service;
?>
    </div>

</body>
</html>
