<?php
// Retrieve the form inputs and store them as variables
$date = $_GET['date'];
$order_of_service = $_GET['order_of_service'];
$opening_hymn = $_GET['opening_hymn'];
$chief_hymn = $_GET['chief_hymn'];
$canticle = isset($_GET['canticle']) ? $_GET['canticle'] : 'magnificat';
$replace_psalm = isset($_GET['replace_psalm']) && $_GET['replace_psalm'] === '1';
$title = ucfirst(str_replace('_', ' ', $order_of_service)) . ' for ' . date_format(date_create($date), 'M d Y');
$prayers = $_GET['override_prayers']; 

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
    
    <!-- Bootstrap stylesheet -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    
    <!-- Common Missal Stylesheet -->
    <link rel="stylesheet" href="missal-common.css">
    
    <style>
        /* Service-specific styles */
        .card-text p {
            margin-bottom: 1rem;
        }
        
        /* Ensure rubrics (em/i tags) use red from common CSS */
        .card-text em,
        .card-text i {
            /* Inherited from .rubric in missal-common.css */
        }
        
        /* Print styles */
        @media print {
            .container {
                box-shadow: none;
                border: none;
            }
            
            .card-body {
                page-break-inside: avoid;
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

    <!-- Bootstrap scripts -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>