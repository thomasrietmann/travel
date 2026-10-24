<?php

namespace App\Console\Commands;

use App\Services\StagingMailProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class StagingMailCheck extends Command
{
    protected $signature = 'staging:mail-check {--smtp : SMTP-TLS und Login ohne Mailversand prüfen} {--imap : IMAP-TLS und Login nur lesend prüfen} {--send : Zusätzlich genau eine Mail an STAGING_MAIL_RECIPIENT senden}';

    protected $description = 'Explizite Mailprüfung nur in der isolierten Staging-Umgebung';

    public function handle(StagingMailProbe $probe): int
    {
        if (! app()->environment('staging') || ! config('staging.checks_enabled')) {
            $this->error('Nur mit APP_ENV=staging und STAGING_CHECKS_ENABLED=true zulässig.');

            return self::FAILURE;
        }
        if ((! $this->option('smtp') && ! $this->option('imap')) || ($this->option('send') && ! $this->option('smtp'))) {
            $this->error('--smtp und/oder --imap auswählen; --send benötigt --smtp.');

            return self::FAILURE;
        }
        $recipient = (string) config('staging.mail_recipient');
        if ($this->option('send') && ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->error('Eine einzelne eigene Testadresse als STAGING_MAIL_RECIPIENT konfigurieren.');

            return self::FAILURE;
        }
        try {
            if ($this->option('smtp')) {
                $probe->smtp();
                $this->info('OK: SMTP-TLS und Authentifizierung.');
            }
            if ($this->option('imap')) {
                $probe->imap();
                $this->info('OK: IMAP-TLS und Mailboxstatus, nur lesend geöffnet.');
            }
            if ($this->option('send')) {
                $reference = (string) Str::uuid();
                $probe->send($recipient, $reference);
                $this->info('SMTP hat die Testmail angenommen. Referenz: '.$reference);
                $this->warn('Eingang und Header im eigenen Testpostfach separat bestätigen; Annahme beweist keine Zustellung.');
            }
        } catch (Throwable) {
            $this->error('Mailprüfung fehlgeschlagen. Host, Zugangsdaten, TLS/Zertifikat und Erweiterungen prüfen. Keine Freigabe; Fehlerdetails werden nicht ausgegeben.');

            return self::FAILURE;
        }
        $this->warn('Dieser Check beweist weder DMARC-Headervertrauen noch einen erfolgreichen Import.');

        return self::SUCCESS;
    }
}
