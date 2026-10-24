<?php

namespace Tests\Support;

use App\Services\ImapMailbox;
use RuntimeException;

class FakeMailbox extends ImapMailbox
{
    public string $sender = 'traveler@example.com';

    public string $auth = 'Authentication-Results: mx.example.test; dmarc=pass header.from=example.com';

    public string $contents = "%PDF-1.4\nbooking";

    public int $size = 1024;

    public int $seen = 0;

    public int $reads = 0;

    public bool $failSeen = false;

    public ?object $mime = null;

    public function open(): void {}

    public function close(): void {}

    public function messages(int $limit): array
    {
        return [1];
    }

    public function headers(int $number): object
    {
        [$local, $host] = explode('@', $this->sender);

        return (object) ['from' => [(object) ['mailbox' => $local, 'host' => $host]], 'subject' => 'Travel', 'Size' => $this->size];
    }

    public function rawHeaders(int $number): string
    {
        return "From: {$this->sender}\r\nMessage-ID: <legacy@example.com>\r\n{$this->auth}\r\n";
    }

    public function key(int $number): string
    {
        return 'imap:test-'.$number;
    }

    public function structure(int $number): object
    {
        return $this->mime ?? (object) ['type' => 3, 'subtype' => 'PDF', 'bytes' => 1024, 'disposition' => 'attachment', 'dparameters' => [(object) ['attribute' => 'filename', 'value' => 'booking.pdf']]];
    }

    public function body(int $number, string $part): string
    {
        $this->reads++;

        return $this->contents;
    }

    public function markSeen(int $number): void
    {
        if ($this->failSeen) {
            throw new RuntimeException('Acknowledgement unavailable');
        }
        $this->seen++;
    }
}
