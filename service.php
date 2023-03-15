<?php
// Retrieve the form inputs and store them as variables
$date = $_POST['date'];
$order_of_service = $_POST['order_of_service'];
$opening_hymn = $_POST['opening_hymn'];
$chief_hymn = $_POST['chief_hymn'];
$canticle = $_POST['canticle'];
$replace_psalm = isset($_POST['replace_psalm']) && $_POST['replace_psalm'] === 'on';
$title = ucfirst($order_of_service) . ' for ' . date_format(date_create($date), 'M d Y');
$prayers = $_POST['override_prayers']; 

$section_classes = [
    'section_class' => "card-body",
    'section_title_class' => 'card-title',
    'section_body_class' => 'card-text'
];

require_once 'class-ServiceBuilder.php'; 
$service = ServiceBuilder::BuildService(new \DateTime($date), $order_of_service, $canticle, [$opening_hymn, $chief_hymn], $replace_psalm, $prayers, $section_classes);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?></title>
    <!-- Add Bootstrap stylesheet -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        @media (min-width: 992px) {
            .card-body-centered {
            margin: 0 auto;
            width: 50%;
        }
        }
        
    </style>
</head>
<body>
    <div class="container mt-5 mx-auto">
        <div class="my-3">
        <h1 class="card-body-centered"><?php echo $title ?></h1>
            <div class="card-body-centered">
                <?php echo $service ?>
            </div>
        </div>
    </div>

    <!-- Add Bootstrap script -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
