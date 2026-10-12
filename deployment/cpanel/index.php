<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

// Standard cPanel layout: /home/USER/public_html and /home/USER/jmac_app.
// If the public directory is nested, set this to the actual PRIVATE app path.
$jmacAppPath = dirname(__DIR__).'/jmac_app';

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = $jmacAppPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $jmacAppPath.'/vendor/autoload.php';
/** @var Application $app */
$app = require_once $jmacAppPath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
