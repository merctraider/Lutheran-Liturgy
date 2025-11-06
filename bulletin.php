<?php
// Include necessary files
require_once 'class-ServiceURLHelper.php';
require_once 'class-ServiceBuilder.php';
require_once 'class-TemplateEngine.php';
require_once 'class-lutherald-BibleGateway.php';
require_once 'calendar/class-lutherald-ChurchYear.php';
require_once 'orders/class-ServiceOrder.php';

// Get settings from request (supports both encoded and legacy GET params)
$settings = ServiceURLHelper::getSettingsFromRequest();

// Validate settings
$validation = ServiceURLHelper::validateSettings($settings);
if (!$validation['valid']) {
    die('Missing required parameters: ' . implode(', ', $validation['missing']));
}

// Parse date
$date = $settings['date'];
if (is_string($date)) {
    $date = new \DateTime($date);
}

// Get day type
$day_type = $settings['day_type'] ?? 'default';

// Get church calendar info
$calendar = \Lutherald\ChurchYear::create_church_year($date);

// Get day info based on day type
switch ($day_type) {
    case 'feast':
        $day_info = $calendar->get_festival($date);
        if ($day_info && isset($day_info['readings'])) {
            $readings = [];
            foreach ($day_info['readings'] as $r) {
                $readings[] = $r;
            }
            $day_info['readings'] = $readings;
        }
        if (!$day_info || empty($day_info)) {
            $day_info = $calendar->retrieve_day_info($date);
        }
        break;
    case 'ember':
    case 'default':
    default:
        $day_info = $calendar->retrieve_day_info($date);
        break;
}

// Load hymnal data
$hymnal = json_decode(file_get_contents(__DIR__ . '/tlh.json'), true);

// Prepare bulletin data
$bulletin_data = [
    'date' => $date->format('l, F j, Y'),
    'day_name' => $day_info['display'] ?? '',
    'order_of_service' => ucfirst(str_replace('_', ' ', $settings['order_of_service'])),
    'season_color' => $day_info['color'] ?? '',
];

// Extract hymn information
$hymn_fields = ['opening_hymn', 'closing_hymn', 'gradual_hymn', 'sermon_hymn',
                'offertory_hymn', 'distribution_hymn', 'communion_hymn'];
$bulletin_data['hymns'] = [];
foreach ($hymn_fields as $field) {
    if (!empty($settings[$field])) {
        $hymn_key = $settings[$field];
        if (isset($hymnal[$hymn_key])) {
            $hymn_title = $hymnal[$hymn_key]['title'] ?? "Hymn $hymn_key";
            $label = ucwords(str_replace('_', ' ', $field));
            $bulletin_data['hymns'][] = [
                'label' => $label,
                'number' => $hymn_key,
                'title' => $hymn_title
            ];
        }
    }
}

// Extract readings
if (isset($day_info['readings']) && is_array($day_info['readings'])) {
    $bulletin_data['readings'] = [];
    foreach ($day_info['readings'] as $reading) {
        if (is_array($reading) && isset($reading['citation'])) {
            $bulletin_data['readings'][] = [
                'type' => $reading['type'] ?? 'Reading',
                'citation' => $reading['citation']
            ];
        }
    }
}

// Extract introit/psalm
if (!empty($day_info['introit'])) {
    $bulletin_data['introit'] = $day_info['introit'];
}

// Extract collect
if (!empty($day_info['collect'])) {
    $bulletin_data['collect'] = $day_info['collect'];
}

// Extract creed (for Chief Service)
if (!empty($settings['creed'])) {
    $bulletin_data['creed'] = ucwords(str_replace('_', ' ', $settings['creed']));
}

// Prepare title
$title = 'Bulletin - ' . ucfirst(str_replace('_', ' ', $settings['order_of_service'])) . ' for ' . $date->format('M d Y');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?></title>

    <style>
        /* Print-friendly bulletin styles */
        body {
            font-family: 'Garamond', 'Georgia', serif;
            font-size: 12pt;
            line-height: 1.5;
            max-width: 8.5in;
            margin: 0 auto;
            padding: 0.5in;
            color: #000;
            background: #fff;
        }

        .bulletin-header {
            text-align: center;
            margin-bottom: 1.5em;
            border-bottom: 2px solid #000;
            padding-bottom: 0.5em;
        }

        .bulletin-header h1 {
            font-size: 20pt;
            margin: 0.2em 0;
            font-weight: bold;
        }

        .bulletin-header .date {
            font-size: 14pt;
            margin: 0.5em 0;
        }

        .bulletin-header .day-name {
            font-size: 16pt;
            font-style: italic;
            margin: 0.3em 0;
        }

        .bulletin-section {
            margin: 1em 0;
            page-break-inside: avoid;
        }

        .bulletin-section h2 {
            font-size: 14pt;
            font-weight: bold;
            margin: 0.5em 0 0.3em 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .bulletin-section ul {
            list-style: none;
            padding: 0;
            margin: 0.3em 0;
        }

        .bulletin-section li {
            margin: 0.3em 0;
            padding-left: 1em;
            text-indent: -1em;
        }

        .hymn-item {
            margin: 0.4em 0;
        }

        .hymn-number {
            font-weight: bold;
            display: inline-block;
            min-width: 3em;
        }

        .reading-item {
            margin: 0.3em 0;
        }

        .reading-type {
            font-weight: bold;
            margin-right: 0.5em;
        }

        .collect-text {
            font-style: italic;
            margin: 0.5em 0;
            text-align: justify;
        }

        @media print {
            body {
                margin: 0;
                padding: 0.5in;
            }

            .no-print {
                display: none;
            }

            @page {
                margin: 0.5in;
            }
        }

        .no-print {
            text-align: center;
            margin: 2em 0;
            padding: 1em;
            background: #f0f0f0;
            border: 1px solid #ccc;
        }

        .no-print button {
            padding: 10px 20px;
            font-size: 14pt;
            cursor: pointer;
            margin: 0 5px;
        }
    </style>
</head>
<body>
    <div class="bulletin-header">
        <h1><?php echo htmlspecialchars($bulletin_data['day_name']); ?></h1>
        <div class="date"><?php echo htmlspecialchars($bulletin_data['date']); ?></div>
        <div class="day-name"><?php echo htmlspecialchars($bulletin_data['order_of_service']); ?></div>
    </div>

    <?php if (!empty($bulletin_data['hymns'])): ?>
    <div class="bulletin-section">
        <h2>Hymns</h2>
        <?php foreach ($bulletin_data['hymns'] as $hymn): ?>
        <div class="hymn-item">
            <span class="hymn-number"><?php echo htmlspecialchars($hymn['number']); ?></span>
            <span class="hymn-title"><?php echo htmlspecialchars($hymn['title']); ?></span>
            <em>(<?php echo htmlspecialchars($hymn['label']); ?>)</em>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulletin_data['readings'])): ?>
    <div class="bulletin-section">
        <h2>Scripture Readings</h2>
        <?php foreach ($bulletin_data['readings'] as $reading): ?>
        <div class="reading-item">
            <span class="reading-type"><?php echo htmlspecialchars($reading['type']); ?>:</span>
            <span class="reading-citation"><?php echo htmlspecialchars($reading['citation']); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulletin_data['introit'])): ?>
    <div class="bulletin-section">
        <h2>Introit / Psalm</h2>
        <div><?php echo htmlspecialchars($bulletin_data['introit']); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulletin_data['creed'])): ?>
    <div class="bulletin-section">
        <h2>Creed</h2>
        <div><?php echo htmlspecialchars($bulletin_data['creed']); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($bulletin_data['collect'])): ?>
    <div class="bulletin-section">
        <h2>Collect</h2>
        <div class="collect-text"><?php echo $bulletin_data['collect']; ?></div>
    </div>
    <?php endif; ?>

    <div class="no-print">
        <button onclick="window.print()">Print Bulletin</button>
        <button onclick="history.back()">Back to Service</button>
    </div>
</body>
</html>
