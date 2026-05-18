#!/usr/bin/env php
<?php

declare(strict_types=1);

$basePath = __DIR__;
$artisanPath = $basePath.'/artisan';
$lockPath = $basePath.'/storage/framework/mail-import-cron.lock';

if (! is_file($artisanPath)) {
    fwrite(STDERR, "Artisan wurde nicht gefunden: {$artisanPath}\n");
    exit(1);
}

if (! chdir($basePath)) {
    fwrite(STDERR, "Projektverzeichnis konnte nicht geoeffnet werden: {$basePath}\n");
    exit(1);
}

$lock = fopen($lockPath, 'c');

if ($lock === false) {
    fwrite(STDERR, "Lock-Datei konnte nicht geoeffnet werden: {$lockPath}\n");
    exit(1);
}

if (! flock($lock, LOCK_EX | LOCK_NB)) {
    echo "Email-Import laeuft bereits. Dieser Cron-Lauf wird uebersprungen.\n";
    exit(0);
}

$limit = getenv('MAIL_IMPORT_CRON_LIMIT') ?: null;

foreach (array_slice($argv ?? [], 1) as $argument) {
    if (preg_match('/^--limit=(\d+)$/', $argument, $matches)) {
        $limit = $matches[1];
    }
}

$command = [
    PHP_BINARY,
    $artisanPath,
    'mail:import',
];

if ($limit !== null && $limit !== '' && ctype_digit((string) $limit)) {
    $command[] = '--limit='.(int) $limit;
}

$escapedCommand = implode(' ', array_map('escapeshellarg', $command));

passthru($escapedCommand, $status);

flock($lock, LOCK_UN);
fclose($lock);

exit($status);
