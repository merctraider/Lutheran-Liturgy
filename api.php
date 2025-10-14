<?php
// API endpoint for settings wizard

// Include required classes
require_once 'calendar/class-lutherald-ChurchYear.php';
require_once 'calendar/class-lutherald-Season.php';
require_once 'calendar/class-lutherald.EmberDays.php';
require_once 'calendar/class-lutherald-ServiceSettingsBuilder.php';
require_once 'calendar/class-lutherald-SettingsAPIHandler.php';

// Create handler and process request
$handler = new \Lutherald\SettingsAPIHandler();
$handler->handle_request();