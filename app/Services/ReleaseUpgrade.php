<?php

namespace App\Services;

use DomainException;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReleaseUpgrade
{
    public const MIGRATIONS = [
        '2026_10_24_000001_create_document_file_deletions_table',
        '2026_10_24_000002_add_email_identity_security',
    ];

    /** Refuse a fresh install, unexpected migrations, or a partially applied MySQL DDL change. */
    public function plan(): array
    {
        $migrator = app(Migrator::class);
        $repository = $migrator->getRepository();
        if (! $repository->repositoryExists()) {
            throw new DomainException('Keine bestehende Migrationshistorie. Dieses Skript ist nur für Bestandsinstallationen.');
        }
        $files = $migrator->getMigrationFiles(database_path('migrations'));
        $ran = $repository->getRan();
        $pending = array_values(array_diff(array_keys($files), $ran));
        if (array_diff(self::MIGRATIONS, array_keys($files)) !== []
            || array_diff($pending, self::MIGRATIONS) !== [] || array_diff($ran, array_keys($files)) !== []) {
            throw new DomainException('Unerwarteter Migrationsstand. Alten Release und Schema zuerst abgleichen.');
        }
        $first = Schema::hasTable('document_file_deletions');
        $second = [
            Schema::hasTable('email_identity_lock'),
            Schema::hasColumn('user_email_aliases', 'verified_at'),
            Schema::hasColumn('imported_mails', 'attempts'),
        ];
        if ($first !== in_array(self::MIGRATIONS[0], $ran, true)
            || $second !== array_fill(0, 3, in_array(self::MIGRATIONS[1], $ran, true))) {
            throw new DomainException('Partielle oder fremde Schemaänderung erkannt. Nicht blind wiederholen; Schema und migrations-Tabelle prüfen.');
        }
        if ($second[0] && ! DB::table('email_identity_lock')->where('id', 1)->exists()) {
            throw new DomainException('Mailidentitäts-Sperrzeile fehlt. Zustand vor Wiederholung manuell klären.');
        }

        return $pending;
    }

    public function evidenceDirectory(string $directory): string
    {
        $real = realpath($directory);
        $project = realpath(base_path());
        if (! $real || ! is_dir($real) || ! is_writable($real) || is_link($directory)
            || $real === $project || str_starts_with($real, $project.DIRECTORY_SEPARATOR)
            || (fileperms($real) & 0077) !== 0) {
            throw new DomainException('Evidenzordner muss existieren, privat (0700), schreibbar und ausserhalb des Projekts/Webroots sein.');
        }

        return $real;
    }

    public function baseline(string $directory, StagingInventory $inventory): array
    {
        $file = $directory.'/upgrade-before.json';
        if (is_link($file)) {
            throw new DomainException('Inventar darf kein Symlink sein.');
        }
        if (file_exists($file)) {
            if (! is_file($file) || filesize($file) > 20 * 1024 * 1024) {
                throw new DomainException('Vorhandenes Inventar ist ungültig.');
            }
            $before = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($before) || $inventory->capture($before) !== $before) {
                throw new DomainException('Bestand weicht vom gespeicherten Ausgangsinventar ab. Keine weitere Änderung.');
            }

            return $before;
        }
        $before = $inventory->capture();
        $json = json_encode($before, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
        $mask = umask(0077);
        try {
            $handle = @fopen($file, 'x');
        } finally {
            umask($mask);
        }
        if ($handle === false) {
            throw new DomainException('Ausgangsinventar konnte nicht exklusiv angelegt werden.');
        }
        try {
            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                throw new DomainException('Ausgangsinventar nicht vollständig gespeichert. Keine Migration ausgeführt.');
            }
        } finally {
            fclose($handle);
        }

        return $before;
    }
}
