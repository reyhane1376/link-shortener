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
// Protection against clickjacking
header("X-Frame-Options: DENY");
// Protection against XSS attacks
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'");
// Protection against MIME type sniffing
header("X-Content-Type-Options: nosniff");
// Force HTTPS
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
// Referrer policy
header("Referrer-Policy: no-referrer-when-downgrade");
// Protection against XSS in older browsers
header("X-XSS-Protection: 1; mode=block");



//helpers
require_once __DIR__ . '/../src/Helpers/helpers.php';

//routing
require_once __DIR__ . '/../routes/api.php';
