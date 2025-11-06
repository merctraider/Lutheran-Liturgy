<?php
/**
 * Bulletin Generator - Creates downloadable .docx bulletins with full service order
 *
 * Required Parameters (via encoded 's' parameter):
 *   - date: Service date
 *   - order_of_service: Service type (matins, vespers, chief_service)
 *   - Additional settings as required by ServiceBuilder
 *
 * Optional GET Parameters:
 *   - show_rubrics: boolean (default: true) - Include liturgical instructions in red italic
 *   - church_name: string - Church name to display at top of bulletin
 *   - display_date: string - Custom date display (doesn't affect liturgical calculations)
 *   - generate: boolean - If true, generate the document; otherwise show form
 */

// Include necessary files
require_once 'class-ServiceURLHelper.php';

// Get settings from request (supports both encoded and legacy GET params)
$settings = ServiceURLHelper::getSettingsFromRequest();

// Validate settings
$validation = ServiceURLHelper::validateSettings($settings);
if (!$validation['valid']) {
    die('Missing required parameters: ' . implode(', ', $validation['missing']));
}

// Check if we should generate the document or show the form
$generate = isset($_GET['generate']) && $_GET['generate'] === 'true';

if (!$generate) {
    // Show the form
    $current_url = $_SERVER['REQUEST_URI'];

    // Parse date for display
    $date = $settings['date'];
    if (is_string($date)) {
        $date = new \DateTime($date);
    }
    $default_date = $date->format('l, F j, Y');

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bulletin Options</title>

        <!-- Bootstrap stylesheet -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">

        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f5f5f5;
                padding: 20px;
            }

            .container {
                max-width: 700px;
                margin: 40px auto;
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }

            h1 {
                font-size: 28px;
                margin-bottom: 10px;
                color: #1a1a1a;
            }

            .subtitle {
                color: #666;
                margin-bottom: 30px;
                font-size: 14px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            label {
                font-weight: 600;
                margin-bottom: 8px;
                display: block;
                color: #333;
            }

            .form-control {
                border-radius: 4px;
                border: 1px solid #ddd;
                padding: 10px 12px;
            }

            .form-check {
                padding-left: 0;
                margin-bottom: 20px;
            }

            .form-check-input {
                margin-right: 8px;
            }

            .btn-primary {
                background: #0066cc;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 4px;
                cursor: pointer;
                width: 100%;
            }

            .btn-primary:hover {
                background: #0052a3;
            }

            .help-text {
                font-size: 13px;
                color: #666;
                margin-top: 5px;
            }

            .back-link {
                display: inline-block;
                margin-bottom: 20px;
                color: #0066cc;
                text-decoration: none;
            }

            .back-link:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <a href="javascript:history.back()" class="back-link">← Back to Service</a>

            <h1>Bulletin Options</h1>
            <p class="subtitle">Configure your bulletin before downloading</p>

            <form method="GET" action="">
                <!-- Preserve encoded settings -->
                <?php if (isset($_GET['s'])): ?>
                    <input type="hidden" name="s" value="<?php echo htmlspecialchars($_GET['s']); ?>">
                <?php endif; ?>

                <!-- Preserve all other settings for backward compatibility -->
                <?php
                foreach ($_GET as $key => $value) {
                    if ($key !== 's' && $key !== 'generate' && $key !== 'church_name' && $key !== 'display_date' && $key !== 'show_rubrics') {
                        echo '<input type="hidden" name="' . htmlspecialchars($key) . '" value="' . htmlspecialchars($value) . '">';
                    }
                }
                ?>

                <input type="hidden" name="generate" value="true">

                <div class="form-group">
                    <label for="church_name">Church Name</label>
                    <input type="text"
                           class="form-control"
                           id="church_name"
                           name="church_name"
                           placeholder="e.g., St. Paul Lutheran Church">
                    <div class="help-text">Optional: Appears as a heading at the top of the bulletin</div>
                </div>

                <div class="form-group">
                    <label for="display_date">Date Display</label>
                    <input type="text"
                           class="form-control"
                           id="display_date"
                           name="display_date"
                           placeholder="<?php echo htmlspecialchars($default_date); ?>">
                    <div class="help-text">Optional: Custom date text (e.g., "Third Sunday in Advent"). Defaults to "<?php echo htmlspecialchars($default_date); ?>"</div>
                </div>

                <div class="form-check">
                    <label>
                        <input type="checkbox"
                               class="form-check-input"
                               id="show_rubrics"
                               name="show_rubrics"
                               value="true"
                               checked>
                        Include rubrics (liturgical instructions in red)
                    </label>
                    <div class="help-text">Uncheck to generate a "propers only" bulletin without instructions</div>
                </div>

                <button type="submit" class="btn btn-primary">Generate Bulletin (.docx)</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// If we get here, we're generating the document
require_once __DIR__ . '/vendor/autoload.php';
require_once 'class-ServiceBuilder.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;

// Get optional bulletin parameters
$show_rubrics = isset($_GET['show_rubrics']) && $_GET['show_rubrics'] === 'true';
$church_name = isset($_GET['church_name']) ? trim($_GET['church_name']) : '';
$display_date = isset($_GET['display_date']) ? trim($_GET['display_date']) : '';

// Prepare section classes for plain rendering (no Bootstrap classes)
$section_classes = [
    'section_class' => "",
    'section_title_class' => "",
    'section_body_class' => ""
];

// Add section classes to settings
$settings['section_classes'] = $section_classes;

// Build the full service
$service_html = ServiceBuilder::BuildService($settings);

// Parse date for filename and display
$date = $settings['date'];
if (is_string($date)) {
    $date = new \DateTime($date);
}

// Use display_date if provided, otherwise use formatted actual date
$date_display = !empty($display_date) ? $display_date : $date->format('l, F j, Y');

// Prepare filename
$order_name = str_replace('_', '-', $settings['order_of_service']);
$filename = 'bulletin-' . $order_name . '-' . $date->format('Y-m-d') . '.docx';

// Create new PHPWord document
$phpWord = new PhpWord();

// Set document properties
$properties = $phpWord->getDocInfo();
$properties->setCreator('Lutheran Liturgy Generator');
$properties->setTitle('Service Bulletin - ' . $date->format('F j, Y'));

// Define styles
$phpWord->addFontStyle('heading1', array('name' => 'Garamond', 'size' => 18, 'bold' => true, 'color' => '000000'));
$phpWord->addFontStyle('heading2', array('name' => 'Garamond', 'size' => 14, 'bold' => true, 'color' => '000000'));
$phpWord->addFontStyle('heading3', array('name' => 'Garamond', 'size' => 12, 'bold' => true, 'color' => '000000'));
$phpWord->addFontStyle('bodyText', array('name' => 'Garamond', 'size' => 11, 'color' => '000000'));
$phpWord->addFontStyle('rubric', array('name' => 'Garamond', 'size' => 11, 'italic' => true, 'color' => 'CC0000'));
$phpWord->addFontStyle('bold', array('name' => 'Garamond', 'size' => 11, 'bold' => true, 'color' => '000000'));

$phpWord->addParagraphStyle('center', array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER, 'spaceAfter' => 120));
$phpWord->addParagraphStyle('normal', array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT, 'spaceAfter' => 120, 'spaceBefore' => 0));
$phpWord->addParagraphStyle('indented', array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::LEFT, 'indentation' => array('left' => 360), 'spaceAfter' => 120));

// Add section to document
$section = $phpWord->addSection(array(
    'marginTop' => 720,    // 0.5 inch
    'marginBottom' => 720,
    'marginLeft' => 720,
    'marginRight' => 720
));

// Add church name if provided
if (!empty($church_name)) {
    $section->addText($church_name, 'heading1', 'center');
    $section->addTextBreak();
}

// Add date display
$section->addText($date_display, 'heading2', 'center');
$section->addTextBreak(2);

// Function to clean HTML and add to Word document
function processHtmlToWord($html, $section, $phpWord, $show_rubrics = true) {
    // Remove audio tags
    $html = preg_replace('/<audio[^>]*>.*?<\/audio>/is', '', $html);
    $html = preg_replace('/\{\{\s*audio:\s*[^}]+\}\}/', '', $html);

    // Load HTML into DOM
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    // Process DOM nodes
    processNode($dom->documentElement, $section, $phpWord, null, $show_rubrics);
}

function processNode($node, $section, $phpWord, $parentStyle = null, $show_rubrics = true) {
    if ($node->nodeType === XML_TEXT_NODE) {
        $text = trim($node->nodeValue);
        if (!empty($text)) {
            $textRun = $section->addTextRun('normal');

            // Apply parent style if available
            if ($parentStyle) {
                $textRun->addText($text, $parentStyle);
            } else {
                $textRun->addText($text, 'bodyText');
            }
        }
        return;
    }

    if ($node->nodeType !== XML_ELEMENT_NODE) {
        return;
    }

    $nodeName = strtolower($node->nodeName);

    switch ($nodeName) {
        case 'h1':
            $text = trim($node->textContent);
            if (!empty($text)) {
                $section->addText($text, 'heading1', 'center');
            }
            break;

        case 'h2':
            $text = trim($node->textContent);
            if (!empty($text)) {
                $section->addText($text, 'heading1', 'center');
            }
            break;

        case 'h3':
            $text = trim($node->textContent);
            if (!empty($text)) {
                $section->addText($text, 'heading2', 'normal');
            }
            break;

        case 'h4':
            $text = trim($node->textContent);
            if (!empty($text)) {
                $section->addText($text, 'heading3', 'normal');
            }
            break;

        case 'p':
            // Check if paragraph contains only rubrics (em/i tags)
            if (!$show_rubrics && paragraphIsOnlyRubric($node)) {
                // Skip this paragraph entirely
                break;
            }
            $textRun = $section->addTextRun('normal');
            processInlineNodes($node, $textRun, $show_rubrics);
            break;

        case 'div':
            // Process children
            foreach ($node->childNodes as $child) {
                processNode($child, $section, $phpWord, $parentStyle, $show_rubrics);
            }
            break;

        case 'br':
            $section->addTextBreak();
            break;

        default:
            // Process children for unknown elements
            foreach ($node->childNodes as $child) {
                processNode($child, $section, $phpWord, $parentStyle, $show_rubrics);
            }
            break;
    }
}

// Helper function to check if a paragraph contains only rubric content
function paragraphIsOnlyRubric($node) {
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_ELEMENT_NODE) {
            $childName = strtolower($child->nodeName);
            if ($childName !== 'em' && $childName !== 'i') {
                return false;
            }
        } elseif ($child->nodeType === XML_TEXT_NODE) {
            $text = trim($child->nodeValue);
            if (!empty($text)) {
                return false;
            }
        }
    }
    return true;
}

function processInlineNodes($node, $textRun, $show_rubrics = true) {
    foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            $text = $child->nodeValue;
            if (!empty($text)) {
                $textRun->addText($text, 'bodyText');
            }
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            $childName = strtolower($child->nodeName);

            switch ($childName) {
                case 'em':
                case 'i':
                    // Skip rubrics if show_rubrics is false
                    if ($show_rubrics) {
                        $text = trim($child->textContent);
                        if (!empty($text)) {
                            $textRun->addText($text, 'rubric');
                        }
                    }
                    break;

                case 'strong':
                case 'b':
                    $text = trim($child->textContent);
                    if (!empty($text)) {
                        $textRun->addText($text, 'bold');
                    }
                    break;

                case 'br':
                    $textRun->addTextBreak();
                    break;

                default:
                    // Recursively process
                    processInlineNodes($child, $textRun, $show_rubrics);
                    break;
            }
        }
    }
}

// Process the service HTML into the Word document
processHtmlToWord($service_html, $section, $phpWord, $show_rubrics);

// Add page break before communion notice
$section->addPageBreak();

// Add Communion Practice Notice
$section->addText('Communion Practice:', array('name' => 'Garamond', 'size' => 12, 'bold' => true, 'underline' => 'single'), 'normal');
$section->addTextBreak();

$communionNotice = 'We believe that Christ is truly present in Holy Communion and, as we try to be faithful to Christ in the serving of this Sacrament, we ask that everyone be examined and instructed by the Pastor before receiving Holy Communion. All confirmed members of this parish, who have been regularly examined by the Pastor, are welcome to partake of the Holy Eucharist today. We also welcome the members who are in good standing of any of the parishes served by the Bishop, Pastors, and Deacons of the Church of the Augustana (CASEA) or The Evangelical Lutheran Diocese of North America (ELDoNA), and who have spoken to the Pastor prior to the service. Members of other Lutheran parishes or other denominations are kindly asked to refrain from communing today. The Pastor would be glad to make an appointment with anyone to discuss our teachings and/or our parish.';

$section->addText($communionNotice, 'bodyText', 'normal');

// Save document
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Save to output
$objWriter->save('php://output');
exit;
