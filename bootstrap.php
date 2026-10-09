<?php
declare(strict_types=1);

// Prevent direct execution notices
error_reporting(E_ALL);
date_default_timezone_set('UTC');

require_once __DIR__ . '/vendor/autoload.php';

// Fallback PSR-4 autoloader for LoganX namespace if vendor autoloader isn't updated
spl_autoload_register(function ($class) {
    $prefix = 'LoganX\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Load configuration
\LoganX\Config::load(__DIR__ . '/.env');

// Set baseline security headers
\LoganX\Security::setSecurityHeaders();
