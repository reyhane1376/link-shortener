<?php
require __DIR__ . '/../vendor/autoload.php';

session_start();
date_default_timezone_set(timezoneId: "Asia/Tehran");


// Set headers for all responses
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');



//helpers
require_once __DIR__ . '/../src/Helpers/helpers.php';

//routing
require_once __DIR__ . '/../routes/api.php';
