<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Support both standard Laravel root and cPanel split layout (~/erp-app and ~/public_html)
$basePath = file_exists(__DIR__.'/../vendor/autoload.php')
    ? __DIR__.'/..'
    : (file_exists(__DIR__.'/../erp-app/vendor/autoload.php')
        ? __DIR__.'/../erp-app'
        : __DIR__.'/..');

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
