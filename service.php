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

// Prepare title
$title = ucfirst(str_replace('_', ' ', $settings['order_of_service'])) . ' for ' . date_format(date_create($settings['date']), 'M d Y');

// Prepare section classes
$section_classes = [
    'section_class' => "card-body",
    'section_title_class' => 'card-title',
    'section_body_class' => 'card-text'
];

// Add section classes to settings
$settings['section_classes'] = $section_classes;

// Build the service - just pass settings directly!
require_once 'class-ServiceBuilder.php'; 
$service = ServiceBuilder::BuildService($settings);

// Generate shareable URL with encoded parameters
$shareableURL = ServiceURLHelper::generateURL($settings, $_SERVER['PHP_SELF']);

// Generate bulletin URL with same parameters
$bulletinURL = ServiceURLHelper::generateURL($settings, '/bulletin.php');
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

        .share-section h3 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .share-url {
            font-family: monospace;
            font-size: 0.9rem;
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 3px;
            word-break: break-all;
        }

        .copy-button {
            margin-top: 10px;
        }

        /* Bulletin section */
        .bulletin-section {
            background: #f0f8ff;
            padding: 15px 20px;
            margin-bottom: 30px;
            border-left: 3px solid #0066cc;
            text-align: center;
        }

        .bulletin-section h3 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #1a1a1a;
        }

        .bulletin-button {
            font-size: 1rem;
            padding: 10px 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        
        
        <!-- Service Content -->
        <?php echo $service; ?>

        <!-- Bulletin Generation Section -->
        <div class="bulletin-section">
            <h3>Download Bulletin</h3>
            <p>Download the complete order of service as a Word document (.docx)</p>
            <a href="<?php echo htmlspecialchars($bulletinURL); ?>" class="btn btn-primary bulletin-button" download>Download Bulletin (.docx)</a>
        </div>

        <!-- Share URL Section -->
        <div class="share-section">
            <h3>Share This Service</h3>
            <div class="share-url" id="shareUrl"><?php echo htmlspecialchars($shareableURL); ?></div>
            <button class="btn btn-sm btn-primary copy-button" onclick="copyShareURL()">Copy URL</button>
        </div>
    </div>
    
    <script>
        function copyShareURL() {
            const urlText = document.getElementById('shareUrl').textContent;
            navigator.clipboard.writeText(urlText).then(function() {
                const btn = document.querySelector('.copy-button');
                const originalText = btn.textContent;
                btn.textContent = 'Copied!';
                btn.classList.add('btn-success');
                btn.classList.remove('btn-primary');
                
                setTimeout(function() {
                    btn.textContent = originalText;
                    btn.classList.add('btn-primary');
                    btn.classList.remove('btn-success');
                }, 2000);
            });
        }
    </script>
</body>
</html>