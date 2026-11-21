<?php

use App\Support\PhpRuntime;

// Run with the same PHP 8.3 CLI and .env as the existing Metanet installation.
// No arguments: read-only checks. Explicit --apply is required for migration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// This guard must run before Composer/Laravel bootstraps, even on an older CLI.
require_once dirname(__DIR__, 2).'/app/Support/PhpRuntime.php';
if (! PhpRuntime::supports()) {
    fwrite(STDERR, PhpRuntime::requirement().' erforderlich; aktiv: '.PHP_VERSION.".\n");
    exit(1);
}
$_SERVER['argv'] = [__FILE__, 'deployment:upgrade', ...array_slice($_SERVER['argv'], 1)];
$_SERVER['argc'] = count($_SERVER['argv']);
require dirname(__DIR__, 2).'/artisan';
