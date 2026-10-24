<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class StagingMailProbe
{
    public function smtp(): void
    {
        $transport = Mail::mailer('smtp')->getSymfonyTransport();
        if (! $transport instanceof EsmtpTransport || ! $transport->isTlsRequired()
            || ! filled(config('mail.mailers.smtp.username')) || ! filled(config('mail.mailers.smtp.password'))) {
            throw new RuntimeException('SMTP mit Authentifizierung und verpflichtendem TLS erforderlich.');
        }
        try {
            $transport->start();
        } finally {
            $transport->stop();
        }
    }

    public function send(string $recipient, string $reference): void
    {
        Mail::mailer('smtp')->raw('TripControl Staging-Abnahme. Referenz: '.$reference, function ($message) use ($recipient, $reference): void {
            $message->to($recipient)->subject('[TripControl STAGING] '.$reference);
        });
    }

    public function imap(): void
    {
        $mailbox = (string) config('mail_import.imap.mailbox');
        if (! function_exists('imap_open') || ! preg_match('/^\{[a-zA-Z0-9.-]+:993\/imap\/ssl\}[^{}\r\n]+$/D', $mailbox)
            || ! filled(config('mail_import.imap.username')) || ! filled(config('mail_import.imap.password'))) {
            throw new RuntimeException('IMAP-Erweiterung und Testkonto mit Port 993/imap/ssl erforderlich.');
        }
        foreach ([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $type) {
            imap_timeout($type, 20);
        }
        // OP_READONLY is essential: never acknowledge, move or delete mail during connectivity checks.
        $connection = @imap_open($mailbox, config('mail_import.imap.username'), config('mail_import.imap.password'), OP_READONLY, 1);
        try {
            if (! $connection || ! @imap_status($connection, $mailbox, SA_UIDVALIDITY)) {
                throw new RuntimeException('IMAP-Verbindung oder Mailboxstatus fehlgeschlagen.');
            }
        } finally {
            if ($connection) {
                @imap_close($connection);
            }
            // Avoid PHP shutdown notices containing server details or credentials.
            imap_errors();
            imap_alerts();
        }
    }
}
