<?php

// Run with the same PHP 8.3 CLI and .env as the existing Metanet installation.
// No arguments: read-only checks. Explicit --apply is required for migration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (version_compare(PHP_VERSION, '8.3.36', '<')) {
    fwrite(STDERR, "PHP mindestens 8.3.36 erforderlich.\n");
    exit(1);
}
$_SERVER['argv'] = [__FILE__, 'deployment:upgrade', ...array_slice($_SERVER['argv'], 1)];
$_SERVER['argc'] = count($_SERVER['argv']);
require dirname(__DIR__, 2).'/artisan';
