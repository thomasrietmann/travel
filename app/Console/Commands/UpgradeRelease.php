<?php

namespace App\Console\Commands;

use App\Services\ProcessLock;
use App\Services\ReleaseUpgrade;
use App\Services\StagingInventory;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpgradeRelease extends Command
{
    protected $signature = 'deployment:upgrade
        {--apply : Die zwei neuen Migrationen tatsächlich ausführen}
        {--expect-database= : Exakt erwarteter Datenbankname, bei --apply erforderlich}
        {--evidence-dir= : Privater vorhandener Ordner (0700) für das dauerhafte Ausgangsinventar}
        {--backup-confirmed : Aktuelles konsistentes Backup vorhanden und Restore geprüft}
        {--writers-stopped : Cron, Queue und andere Schreibprozesse gestoppt und ausgelaufen}
        {--copy-documents : Nach der Migration alte Dokumente geprüft privat kopieren; Quellen erhalten}';

    protected $description = 'Kontrolliertes Bestandsupgrade für den Sicherheitsrelease 2026-10-24; standardmässig nur prüfen';

    public function handle(ReleaseUpgrade $upgrade, StagingInventory $inventory, ProcessLock $lock): int
    {
        try {
            if (! app()->environment(['production', 'staging']) || config('app.debug')
                || ! filled(config('app.key')) || config('hashing.driver') !== 'bcrypt'
                || version_compare(PHP_VERSION, '8.3.36', '<') || DB::connection()->getDriverName() !== 'mysql') {
                throw new DomainException('Erforderlich: production/staging ohne Debug, bestehender APP_KEY, bcrypt, PHP >= 8.3.36 und MySQL.');
            }
            if (app()->environment('production') && config('staging.checks_enabled')) {
                throw new DomainException('STAGING_CHECKS_ENABLED muss auf Produktion false sein.');
            }
            $database = DB::connection()->getDatabaseName();
            $expected = $this->option('expect-database');
            if (($expected !== null && $expected !== $database) || ($this->option('apply') && ! filled($expected))) {
                throw new DomainException('--expect-database muss exakt der aktiven Bestandsdatenbank entsprechen.');
            }
            if (collect(DB::select('SHOW TABLE STATUS'))->contains(fn ($table) => $table->Engine && $table->Engine !== 'InnoDB')) {
                throw new DomainException('Nicht-transaktionale Tabellen gefunden. Engine-Umstellung separat prüfen.');
            }
            $pending = $upgrade->plan();
            $this->info('Bestandsdatenbank: '.$database.'; ausstehende Release-Migrationen: '.count($pending));
            foreach ($pending as $name) {
                $this->line($name);
            }
            if (! $this->option('apply')) {
                if ($this->option('copy-documents')) {
                    throw new DomainException('--copy-documents benötigt --apply.');
                }
                $inventory->capture();
                $this->info('Prüflauf erfolgreich. Keine Migration, keine Dateiänderung.');
                $this->warn('Kein Backupnachweis. Vor Ausführung Backup/Restore und vollständige Schreibpause sicherstellen.');

                return self::SUCCESS;
            }
            if (! app()->isDownForMaintenance() || ! $this->option('backup-confirmed') || ! $this->option('writers-stopped')) {
                throw new DomainException('Ausführung nur im Wartungsmodus mit --backup-confirmed und --writers-stopped.');
            }
            $directory = $upgrade->evidenceDirectory((string) $this->option('evidence-dir'));

            return $lock->run('deployment-upgrade-20261024', fn () => $lock->run('mail-import-service', function () use ($upgrade, $inventory, $directory): int {
                $upgrade->plan();
                $before = $upgrade->baseline($directory, $inventory);
                $paths = array_map(fn ($name) => 'database/migrations/'.$name.'.php', ReleaseUpgrade::MIGRATIONS);
                if ($this->call('migrate', ['--path' => $paths, '--force' => true]) !== self::SUCCESS) {
                    throw new DomainException('Migration fehlgeschlagen. Wartung beibehalten und MySQL-DDL-/Migrationsstand prüfen.');
                }
                if ($upgrade->plan() !== []) {
                    throw new DomainException('Release-Migrationen nicht vollständig abgeschlossen.');
                }
                if ($this->option('copy-documents') && $this->call('documents:migrate-private', ['--apply' => true]) !== self::SUCCESS) {
                    throw new DomainException('Dokumentkopie fehlgeschlagen. Quellen erhalten; Ablage vor Wiederholung prüfen.');
                }
                if ($inventory->capture($before) !== $before) {
                    throw new DomainException('Bestandsvergleich nach Upgrade fehlgeschlagen. Wartung beibehalten.');
                }
                $this->info('Upgrade erfolgreich. Bestandszeilen, APP_KEY-Fingerabdruck und Dokumentbytes unverändert.');
                $this->warn('Wartung bleibt aktiv. Caches, deployment:check und HTTP-Smoke-Tests gemäss Anleitung durchführen; Web/Cron separat freigeben.');

                return self::SUCCESS;
            }));
        } catch (Throwable $exception) {
            $this->error($exception instanceof DomainException ? $exception->getMessage() : 'Upgrade-Prüfung fehlgeschlagen. Verbindung, Schema, Ablagen und private Inventare prüfen. Keine automatische Rücksetzung.');
            $this->warn('Bei begonnenem Upgrade Wartung und Cron-Stopp beibehalten. Es erfolgt kein automatisches Rollback, Restore oder Löschen von Bestandsdaten.');

            return self::FAILURE;
        }
    }
}
