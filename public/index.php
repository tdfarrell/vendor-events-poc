<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 * Find the app folder. Normally it's the folder above this one. On shared
 * hosting (Bluehost), the contents of public/ are copied into the domain's
 * web folder and the app lives in a private folder named "vendor-events-app"
 * in your home directory, outside anything the web can reach.
 */
$appDir = null;
foreach ([
    dirname(__DIR__),                           // standard layout: app/public
    dirname(__DIR__).'/vendor-events-app',      // web folder directly in home
    dirname(__DIR__, 2).'/vendor-events-app',   // web folder inside public_html
    dirname(__DIR__, 3).'/vendor-events-app',
] as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php') && is_file($candidate.'/vendor/autoload.php')) {
        $appDir = $candidate;
        break;
    }
}
if ($appDir === null) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Setup needed: couldn't find the vendor-events-app folder. Put it in your home directory (next to public_html).\n";
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appDir.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appDir.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appDir.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
