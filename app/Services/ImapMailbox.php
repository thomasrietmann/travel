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
        // Keep verification, MIME reads and acknowledgement on the same message after an expunge.
        return array_slice(imap_search($this->connection, config('mail_import.imap.search', 'UNSEEN'), SE_UID) ?: [], 0, $limit);
    }

    public function headers(int $uid): object
    {
        // imap_headerinfo only accepts sequence numbers. Check the mapping across this one call.
        $number = imap_msgno($this->connection, $uid);
        $headers = $number ? imap_headerinfo($this->connection, $number) : false;
        if (! $headers || imap_uid($this->connection, $number) !== $uid) {
            throw new RuntimeException('Mail-Header fehlen.');
        }

        return $headers;
    }

    public function rawHeaders(int $uid): string
    {
        return imap_fetchheader($this->connection, $uid, FT_UID) ?: throw new RuntimeException('Mail-Header fehlen.');
    }

    public function structure(int $uid): object
    {
        return imap_fetchstructure($this->connection, $uid, FT_UID) ?: throw new RuntimeException('MIME-Struktur fehlt.');
    }

    public function body(int $uid, string $part): string
    {
        $body = $part === '' ? imap_body($this->connection, $uid, FT_UID | FT_PEEK) : imap_fetchbody($this->connection, $uid, $part, FT_UID | FT_PEEK);

        return $body === false ? throw new RuntimeException('Mailinhalt fehlt.') : $body;
    }

    public function key(int $uid): string
    {
        $status = imap_status($this->connection, config('mail_import.imap.mailbox'), SA_UIDVALIDITY);
        if (! $status || ! $status->uidvalidity) {
            throw new RuntimeException('Mailbox-Identität fehlt.');
        }

        return 'imap:'.hash('sha256', config('mail_import.imap.mailbox').'|'.config('mail_import.imap.username').'|'.$status->uidvalidity.'|'.$uid);
    }

    public function markSeen(int $uid): void
    {
        if (config('mail_import.mark_seen') && ! imap_setflag_full($this->connection, (string) $uid, '\\Seen', ST_UID)) {
            throw new RuntimeException('Lesestatus konnte nicht gespeichert werden.');
        }
    }
}
