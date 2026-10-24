<?php

namespace App\Services;

use RuntimeException;

class ProcessLock
{
    /** All web/CLI entry points use the same persistent directory on the Metanet host. */
    public function run(string $name, callable $callback): mixed
    {
        $directory = config('app.lock_path');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Sperrverzeichnis ist nicht verfügbar.');
        }
        $handle = fopen($directory.'/'.$name.'.lock', 'c');
        if ($handle === false) {
            throw new RuntimeException('Prozesssperre ist nicht verfügbar.');
        }
        try {
            if (! flock($handle, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Dieser Vorgang läuft bereits.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
