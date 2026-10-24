<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DeploymentCheck extends Command
{
    protected $signature = 'deployment:check {--staging : Explizit eine isolierte Staging-Umgebung prüfen} {--mail : Mailimport-Voraussetzungen zusätzlich prüfen} {--probe-storage : Eine temporäre private Testdatei schreiben und entfernen}';

    protected $description = 'Produktionskonfiguration, Dokumentlesbarkeit und bekannte Kontorisiken ohne Datenänderung prüfen';

    private int $failures = 0;

    public function handle(DocumentStorage $storage): int
    {
        $this->failures = 0;
        $expectedEnvironment = $this->option('staging') ? 'staging' : 'production';
        if (! app()->environment($expectedEnvironment)) {
            $this->error('Falsche Umgebung; erwartet: '.$expectedEnvironment.'. Keine Prüfungen ausgeführt.');

            return self::FAILURE;
        }
        $this->check(version_compare(PHP_VERSION, '8.3.36', '>='), 'PHP mindestens 8.3.36');
        $this->check(app()->environment($expectedEnvironment) && ! config('app.debug'), $expectedEnvironment.' ohne Debug');
        $this->check(filled(config('app.key')), 'Bestehender APP_KEY vorhanden (Übereinstimmung mit Backup separat prüfen)');
        $this->check(str_starts_with(config('app.url'), 'https://') && config('session.secure'), 'HTTPS-URL und sichere Session-Cookies');
        $this->check(config('hashing.driver') === 'bcrypt', 'Bestehendes bcrypt-Verfahren erhalten');
        $this->check(DB::connection()->getDriverName() === 'mysql', 'MySQL-Verbindung');
        try {
            DB::select('SELECT 1');
            $this->check(DB::table('email_identity_lock')->where('id', 1)->exists(), 'Neue Migrationen vorhanden');
            if (DB::connection()->getDriverName() === 'mysql') {
                $nonTransactional = collect(DB::select('SHOW TABLE STATUS'))->filter(fn ($table) => $table->Engine && $table->Engine !== 'InnoDB')->count();
                $this->check($nonTransactional === 0, 'Tabellen verwenden InnoDB');
            }
            $collisions = DB::table('users')->selectRaw('LOWER(email) AS address')->unionAll(DB::table('user_email_aliases')->selectRaw('LOWER(email) AS address'));
            $count = DB::query()->fromSub($collisions, 'identities')->select('address')->groupBy('address')->havingRaw('COUNT(*) > 1')->get()->count();
            $this->check($count === 0, 'Keine kollidierenden Mailidentitäten (Anzahl: '.$count.')');
            $weak = User::query()->where(fn ($query) => $query->where('is_admin', true)->orWhereIn('email', ['admin@tripcontrol.test', 'demo@tripcontrol.test']))->get()
                ->filter(fn (User $user) => Hash::check('password', $user->password))->count();
            $this->check($weak === 0, 'Keine bekannten Demo-Passwörter bei Admin-/Demokonten');
        } catch (Throwable) {
            $this->check(false, 'Datenbank-/Kontoprüfung fehlgeschlagen; Schema und Verbindung prüfen');
        }
        $missing = 0;
        $conflicts = 0;
        foreach (Document::query()->lazyById() as $document) {
            try {
                $paths = $storage->paths($document->file_path);
                if ($paths === []) {
                    $missing++;
                } elseif (count(array_unique(array_map($storage->checksum(...), $paths))) > 1) {
                    $conflicts++;
                }
            } catch (Throwable) {
                $missing++;
            }
        }
        $this->check($missing === 0 && $conflicts === 0, "Dokumente lesbar: {$missing} fehlend/ungültig, {$conflicts} widersprüchlich");
        $private = realpath(Storage::disk('local')->path(''));
        $public = realpath(public_path());
        $this->check($private && $public && $private !== $public && ! str_starts_with($private, $public.'/'), 'Private Ablage ausserhalb des Webroots');
        $locks = config('app.lock_path');
        $this->check(is_dir($locks) && is_writable($locks), 'Gemeinsames Prozesssperrverzeichnis schreibbar');
        $this->check(config('cache.default') === 'database', 'Gemeinsamer Datenbank-Cache für Limits');
        if ($this->option('probe-storage')) {
            $path = 'deployment-probe-'.Str::random(32);
            try {
                $bytes = random_bytes(64);
                $this->check(Storage::disk('local')->put($path, $bytes) && Storage::disk('local')->get($path) === $bytes, 'Private Schreib-/Leseprobe');
            } finally {
                $this->check(Storage::disk('local')->delete($path), 'Testdatei entfernt');
            }
        }
        if ($this->option('mail')) {
            $this->check(extension_loaded('imap'), 'IMAP-Erweiterung');
            $this->check(config('mail_import.enabled') && config('mail_import.authentication_results_trusted') && filled(config('mail_import.trusted_authserv_ids')), 'Import und geprüfte Header-Vertrauensgrenze konfiguriert');
            $this->check(filled(config('ai.openai.api_key')), 'AI-Schlüssel für Mailimport vorhanden');
            $this->check(config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.username')) && filled(config('mail.mailers.smtp.password')) && filled(config('mail.from.address')), 'SMTP für Adressbestätigung konfiguriert');
            $this->warn('Dieser Check beweist weder SMTP-Zustellung noch Header-Vertrauen. Echte Positiv-/Negativtests sind Freigabevoraussetzung.');
        }
        $this->warn('Zusätzlich Web-/Cron-Pfade und öffentliche URL-Sperren über HTTP prüfen. Ein erfolgreicher CLI-Check ist keine Deploymentfreigabe.');

        return $this->failures ? self::FAILURE : self::SUCCESS;
    }

    private function check(bool $ok, string $message): void
    {
        if ($ok) {
            $this->info('OK: '.$message);
        } else {
            $this->error('FEHLER: '.$message);
            $this->failures++;
        }
    }
}
