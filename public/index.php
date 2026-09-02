<?php

use Illuminate\Http\Request;

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// php artisan serve uses PHP's built-in cli-server SAPI. On Windows, PHP 8.5
// reads every vendor file from disk on every request (no persistent opcache),
// and antivirus scanning makes this slow enough to hit the 30-second limit.
// Disable the kill-switch for the dev server only — production uses PHP-FPM
// which has its own timeout configured separately.
if (PHP_SAPI === 'cli-server') {
    set_time_limit(0);
}

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
