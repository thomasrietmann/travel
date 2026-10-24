<?php

namespace App\Console\Commands;

use App\Services\StagingInventory;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class StagingInventoryCheck extends Command
{
    protected $signature = 'staging:inventory {file : Private JSON-Datei ausserhalb des Projektordners} {--verify : Bestehendes Inventar vergleichen statt anlegen}';

    protected $description = 'Bestandszeilen und Dokumentbytes für Restore/Upgrade prüfen, ohne Daten zu verändern';

    public function handle(StagingInventory $inventory): int
    {
        if (! app()->environment('staging') || ! config('staging.checks_enabled')) {
            $this->error('Nur mit APP_ENV=staging und STAGING_CHECKS_ENABLED=true zulässig.');

            return self::FAILURE;
        }
        try {
            $file = (string) $this->argument('file');
            $parent = realpath(dirname($file));
            $project = realpath(base_path());
            if (! $parent || ! $project || $parent === $project || str_starts_with($parent, $project.DIRECTORY_SEPARATOR)
                || is_link($file)) {
                throw new RuntimeException('Privaten Inventarordner ausserhalb des Projekts verwenden.');
            }
            if ($this->option('verify')) {
                if (! is_file($file) || filesize($file) > 20 * 1024 * 1024) {
                    throw new RuntimeException('Inventar fehlt oder ist zu gross.');
                }
                $before = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($before) || $inventory->capture($before) !== $before) {
                    throw new RuntimeException('Bestandsvergleich fehlgeschlagen. Keine Freigabe.');
                }
                $this->info('OK: APP_KEY-Fingerabdruck, Bestandszeilen und Dokumentbytes stimmen überein.');
            } else {
                $json = json_encode($inventory->capture(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
                $mask = umask(0077);
                try {
                    $handle = @fopen($file, 'x');
                } finally {
                    umask($mask);
                }
                if ($handle === false) {
                    throw new RuntimeException('Inventar existiert bereits oder ist nicht schreibbar.');
                }
                try {
                    if (fwrite($handle, $json) !== strlen($json)) {
                        throw new RuntimeException('Inventar nicht vollständig geschrieben.');
                    }
                } finally {
                    fclose($handle);
                }
                $this->info('OK: Privates Inventar erstellt; keine Datenbank-/Dokumentänderung.');
            }
        } catch (Throwable) {
            $this->error('Inventarprüfung fehlgeschlagen: Ablage, Schema, APP_KEY, Bestandszeilen und Dokumentkopien prüfen. Keine Freigabe.');

            return self::FAILURE;
        }
        $this->warn('Nur bei angehaltenen Schreibprozessen aussagekräftig. Erfasst Geschäftstabellen und referenzierte Dokumente, nicht Sessions/Cache/Jobs oder unreferenzierte Dateien.');

        return self::SUCCESS;
    }
}
