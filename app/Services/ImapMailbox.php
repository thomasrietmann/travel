<?php

namespace App\Services;

use RuntimeException;

class ImapMailbox
{
    private mixed $connection = null;

    public function open(): void
    {
        if (! function_exists('imap_open')) {
            throw new RuntimeException('Die PHP-IMAP-Erweiterung fehlt.');
        }
        $mailbox = (string) config('mail_import.imap.mailbox');
        if (! str_contains($mailbox, '/ssl') || str_contains($mailbox, '/novalidate-cert')) {
            throw new RuntimeException('IMAP benötigt TLS mit Zertifikatsprüfung.');
        }
        foreach ([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $type) {
            imap_timeout($type, 20);
        }
        $this->connection = @imap_open($mailbox, config('mail_import.imap.username'), config('mail_import.imap.password'));
        if (! $this->connection) {
            throw new RuntimeException('Mailkonto konnte nicht geöffnet werden.');
        }
    }

    public function close(): void
    {
        if ($this->connection) {
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    public function messages(int $limit): array
    {
        return array_slice(imap_search($this->connection, config('mail_import.imap.search', 'UNSEEN')) ?: [], 0, $limit);
    }

    public function headers(int $number): object
    {
        return imap_headerinfo($this->connection, $number) ?: throw new RuntimeException('Mail-Header fehlen.');
    }

    public function rawHeaders(int $number): string
    {
        return imap_fetchheader($this->connection, $number) ?: throw new RuntimeException('Mail-Header fehlen.');
    }

    public function structure(int $number): object
    {
        return imap_fetchstructure($this->connection, $number) ?: throw new RuntimeException('MIME-Struktur fehlt.');
    }

    public function body(int $number, string $part): string
    {
        $body = $part === '' ? imap_body($this->connection, $number, FT_PEEK) : imap_fetchbody($this->connection, $number, $part, FT_PEEK);

        return $body === false ? throw new RuntimeException('Mailinhalt fehlt.') : $body;
    }

    public function key(int $number): string
    {
        $status = imap_status($this->connection, config('mail_import.imap.mailbox'), SA_UIDVALIDITY);
        if (! $status || ! $status->uidvalidity) {
            throw new RuntimeException('Mailbox-Identität fehlt.');
        }
        $uid = imap_uid($this->connection, $number);
        if (! $uid) {
            throw new RuntimeException('Nachrichten-Identität fehlt.');
        }

        return 'imap:'.hash('sha256', config('mail_import.imap.mailbox').'|'.config('mail_import.imap.username').'|'.$status->uidvalidity.'|'.$uid);
    }

    public function markSeen(int $number): void
    {
        if (config('mail_import.mark_seen') && ! imap_setflag_full($this->connection, (string) $number, '\\Seen')) {
            throw new RuntimeException('Lesestatus konnte nicht gespeichert werden.');
        }
    }
}
