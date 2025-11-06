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

            <!-- Bulletin Options -->
            <div class="bulletin-options" style="margin: 15px 0; text-align: left; max-width: 600px; margin-left: auto; margin-right: auto;">
                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="church_name" style="display: block; font-weight: bold; margin-bottom: 5px;">Church Name (optional):</label>
                    <input type="text" id="church_name" class="form-control" placeholder="e.g., St. Paul Lutheran Church" style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 10px;">
                    <label for="display_date" style="display: block; font-weight: bold; margin-bottom: 5px;">Custom Date Display (optional):</label>
                    <input type="text" id="display_date" class="form-control" placeholder="e.g., Third Sunday in Advent" style="width: 100%;">
                </div>

                <div class="form-check" style="margin-bottom: 15px;">
                    <input type="checkbox" id="show_rubrics" class="form-check-input" checked>
                    <label for="show_rubrics" class="form-check-label" style="font-weight: normal;">
                        Include rubrics (liturgical instructions in red)
                    </label>
                </div>
            </div>

            <button class="btn btn-primary bulletin-button" onclick="downloadBulletin()">Download Bulletin (.docx)</button>
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

        function downloadBulletin() {
            // Get base bulletin URL
            const baseURL = '<?php echo htmlspecialchars($bulletinURL); ?>';

            // Get optional parameters
            const churchName = document.getElementById('church_name').value.trim();
            const displayDate = document.getElementById('display_date').value.trim();
            const showRubrics = document.getElementById('show_rubrics').checked;

            // Build URL with parameters
            let url = baseURL;
            const params = [];

            if (churchName) {
                params.push('church_name=' + encodeURIComponent(churchName));
            }
            if (displayDate) {
                params.push('display_date=' + encodeURIComponent(displayDate));
            }
            if (!showRubrics) {
                params.push('show_rubrics=false');
            }

            if (params.length > 0) {
                url += (url.includes('?') ? '&' : '?') + params.join('&');
            }

            // Open URL to trigger download
            window.location.href = url;
        }
    </script>
</body>
</html>