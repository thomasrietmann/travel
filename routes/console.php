<?php

use App\Models\ImportedMail;
use App\Models\User;
use App\Services\EmailIdentity;
use App\Services\IncomingMailImporter;
use App\Services\MailImportLogger;
use App\Services\ProcessLock;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mail:import {--limit= : Maximale Anzahl Mails fuer diesen Lauf}', function () {
    $logger = app(MailImportLogger::class);

    $logger->info('Email-Import per Artisan wurde gestartet.');

    $stats = app(IncomingMailImporter::class)->import((int) ($this->option('limit') ?: 0));

    $logger->info('Email-Import per Artisan wurde abgeschlossen.', $stats);

    $this->info("{$stats['imported']} Mails importiert.");
    $this->info("{$stats['ignored']} Mails ignoriert.");

    if ($stats['failed'] > 0) {
        $this->warn("{$stats['failed']} Mails konnten nicht importiert werden.");

        return 1;
    }
    if ($stats['deferred'] > 0) {
        $this->warn("{$stats['deferred']} Mails warten auf Wiederholung oder Klärung.");

        return 1;
    }

    return 0;
})->purpose('Import forwarded travel mails from the configured mailbox');

Artisan::command('admin:create {email} {--name=Administrator}', function () {
    $email = mb_strtolower(trim($this->argument('email')));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Ungültige E-Mail-Adresse.');

        return 1;
    }
    $password = $this->secret('Neues Admin-Passwort (mindestens 12 Zeichen)');
    if (strlen((string) $password) < 12 || $password !== $this->secret('Passwort wiederholen')) {
        $this->error('Passwörter stimmen nicht überein oder sind zu kurz.');

        return 1;
    }
    app(EmailIdentity::class)->create($email, fn (string $email) => User::query()->create([
        'name' => $this->option('name'), 'email' => $email,
        'password' => Hash::make($password), 'is_admin' => true,
    ]));
    $this->info('Neues Administratorkonto erstellt.');

    return 0;
})->purpose('Create a new administrator without modifying existing users');

Artisan::command('mail:retry {id}', function () {
    return app(ProcessLock::class)->run('mail-import-service', function () {
        $mail = ImportedMail::query()->findOrFail($this->argument('id'));
        if (! in_array($mail->status, ['failed', 'blocked', 'processing'], true)) {
            $this->error('Nur fehlgeschlagene oder blockierte Importe können wiederholt werden.');

            return 1;
        }
        $mail->update(['status' => 'pending', 'attempts' => 0]);
        $this->info('Import für den nächsten Lauf freigegeben. Die Mail muss noch ungelesen im Postfach liegen.');

        return 0;
    });
})->purpose('Retry a retained mail after resolving its error');
