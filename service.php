<?php
// Include URL helper
require_once 'class-ServiceURLHelper.php';

// Get settings from request (supports both encoded and legacy GET params)
$settings = ServiceURLHelper::getSettingsFromRequest();

// Validate settings
$validation = ServiceURLHelper::validateSettings($settings);
if (!$validation['valid']) {
    die('Missing required parameters: ' . implode(', ', $validation['missing']));
}

// Extract settings
$date = $settings['date'];
$order_of_service = $settings['order_of_service'];
$opening_hymn = $settings['opening_hymn'];
$chief_hymn = $settings['chief_hymn'] ?? 'default';
$canticle = $settings['canticle'] ?? 'magnificat';
$replace_psalm = $settings['replace_psalm'] ?? false;
$prayers = $settings['override_prayers'];
$day_type = $settings['day_type'] ?? 'default';

$title = ucfirst(str_replace('_', ' ', $order_of_service)) . ' for ' . date_format(date_create($date), 'M d Y');

$section_classes = [
    'section_class' => "card-body",
    'section_title_class' => 'card-title',
    'section_body_class' => 'card-text'
];

require_once 'class-ServiceBuilder.php'; 
$service = ServiceBuilder::BuildService(new \DateTime($date), $order_of_service, $canticle, [$opening_hymn, $chief_hymn], $replace_psalm, $prayers, $section_classes, $day_type);

// Generate shareable URL with encoded parameters
$shareableURL = ServiceURLHelper::generateURL($settings, $_SERVER['PHP_SELF']);
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
        
        /* Share URL section */
        .share-section {
            background: #f9f7f2;
            padding: 15px 20px;
            margin-bottom: 30px;
            border-left: 3px solid #1a1a1a;
            text-align: center;
        }
        
        .share-url {
            font-family: 'Courier New', monospace;
            background: #fefdfb;
            padding: 10px;
            border: 1px solid #d4c5a9;
            display: inline-block;
            margin: 10px 0;
            word-break: break-all;
            font-size: 0.9rem;
        }
        
        .copy-btn {
            background: #1a1a1a;
            color: #fefdfb;
            border: none;
            padding: 8px 20px;
            font-family: 'Garamond', 'Georgia', serif;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #000;
        }
        
        .copy-btn.copied {
            background: #2d5016;
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
            
            .share-section {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-5 mx-auto">
        <div class="my-3">
            <h1 class="card-body-centered"><?php echo $title ?></h1>
            
            <!-- Shareable URL Section -->
            <div class="share-section card-body-centered">
                <p><strong>Share this service:</strong></p>
                <button class="copy-btn" onclick="copyURL()">Copy Link</button>
            </div>
            
            <div class="card-body-centered">
                <?php echo $service ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap scripts -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <script>
        function copyURL() {
            const urlText = document.getElementById('shareUrl').textContent;
            const btn = event.target;
            
            // Create temporary input
            const tempInput = document.createElement('input');
            tempInput.value = urlText;
            document.body.appendChild(tempInput);
            
            // Select and copy
            tempInput.select();
            tempInput.setSelectionRange(0, 99999); // For mobile
            document.execCommand('copy');
            
            // Remove temporary input
            document.body.removeChild(tempInput);
            
            // Show feedback
            const originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('copied');
            }, 2000);
        }
    </script>
</body>
</html>