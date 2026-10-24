#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Console\Input\ArgvInput;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

define('LARAVEL_START', microtime(true));
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$arguments = [__DIR__.'/artisan', 'mail:import'];
$limit = getenv('MAIL_IMPORT_CRON_LIMIT');
foreach (array_slice($argv, 1) as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches)) {
        $limit = $matches[1];
    }
}
if ($limit !== false && ctype_digit((string) $limit)) {
    $arguments[] = '--limit='.$limit;
}
// The service owns the shared lock, including direct Artisan and administrator starts.
exit($app->handleCommand(new ArgvInput($arguments)));
