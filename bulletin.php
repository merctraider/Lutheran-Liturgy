<?php
// Include composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Include necessary files
require_once 'class-ServiceURLHelper.php';
require_once 'class-ServiceBuilder.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;

// Get settings from request (supports both encoded and legacy GET params)
$settings = ServiceURLHelper::getSettingsFromRequest();

// Validate settings
$validation = ServiceURLHelper::validateSettings($settings);
if (!$validation['valid']) {
    die('Missing required parameters: ' . implode(', ', $validation['missing']));
}

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

// Parse date for filename
$date = $settings['date'];
if (is_string($date)) {
    $date = new \DateTime($date);
}

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

// Function to clean HTML and add to Word document
function processHtmlToWord($html, $section, $phpWord) {
    // Remove audio tags
    $html = preg_replace('/<audio[^>]*>.*?<\/audio>/is', '', $html);
    $html = preg_replace('/\{\{\s*audio:\s*[^}]+\}\}/', '', $html);

    // Load HTML into DOM
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    // Process DOM nodes
    processNode($dom->documentElement, $section, $phpWord);
}

function processNode($node, $section, $phpWord, $parentStyle = null) {
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
            $textRun = $section->addTextRun('normal');
            processInlineNodes($node, $textRun);
            break;

        case 'div':
            // Process children
            foreach ($node->childNodes as $child) {
                processNode($child, $section, $phpWord, $parentStyle);
            }
            break;

        case 'br':
            $section->addTextBreak();
            break;

        default:
            // Process children for unknown elements
            foreach ($node->childNodes as $child) {
                processNode($child, $section, $phpWord, $parentStyle);
            }
            break;
    }
}

function processInlineNodes($node, $textRun) {
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
                    $text = trim($child->textContent);
                    if (!empty($text)) {
                        $textRun->addText($text, 'rubric');
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
                    processInlineNodes($child, $textRun);
                    break;
            }
        }
    }
}

// Process the service HTML into the Word document
processHtmlToWord($service_html, $section, $phpWord);

// Save document
$objWriter = IOFactory::createWriter($phpWord, 'Word2007');

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Save to output
$objWriter->save('php://output');
exit;
