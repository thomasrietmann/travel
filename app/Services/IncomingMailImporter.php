<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Document;
use App\Models\ImportedMail;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserEmailAlias;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class IncomingMailImporter
{
    public function __construct(
        private readonly MailAiExtractor $extractor,
        private readonly TripSummaryGenerator $summaryGenerator,
    ) {}

    public function import(int $limit = 0): array
    {
        if (! config('mail_import.enabled')) {
            throw new RuntimeException('MAIL_IMPORT_ENABLED ist nicht aktiv.');
        }

        if (! function_exists('imap_open')) {
            throw new RuntimeException('Die PHP-IMAP-Erweiterung ist nicht verfügbar.');
        }

        $mailbox = config('mail_import.imap.mailbox');
        $username = config('mail_import.imap.username');
        $password = config('mail_import.imap.password');

        if (! $mailbox || ! $username || ! $password) {
            throw new RuntimeException('MAIL_IMPORT_IMAP_MAILBOX, MAIL_IMPORT_IMAP_USERNAME und MAIL_IMPORT_IMAP_PASSWORD muessen gesetzt sein.');
        }

        $imap = @imap_open($mailbox, $username, $password);

        if (! $imap) {
            throw new RuntimeException('Mailkonto konnte nicht geöffnet werden: '.imap_last_error());
        }

        $stats = ['imported' => 0, 'ignored' => 0, 'failed' => 0];

        try {
            $messageNumbers = imap_search($imap, config('mail_import.imap.search', 'UNSEEN')) ?: [];
            $messageNumbers = array_slice($messageNumbers, 0, $limit ?: config('mail_import.max_messages', 10));

            foreach ($messageNumbers as $messageNumber) {
                try {
                    $result = $this->importMessage($imap, (int) $messageNumber);
                    $stats[$result]++;
                } catch (Throwable) {
                    $stats['failed']++;
                }
            }
        } finally {
            imap_close($imap);
        }

        return $stats;
    }

    private function importMessage($imap, int $messageNumber): string
    {
        $headers = imap_headerinfo($imap, $messageNumber);

        if (! $headers) {
            throw new RuntimeException('Mail-Header konnten nicht gelesen werden.');
        }

        $messageId = $this->messageId($imap, $messageNumber);

        if (ImportedMail::query()->where('message_id', $messageId)->exists()) {
            $this->markSeen($imap, $messageNumber);

            return 'ignored';
        }

        $senderEmail = $this->senderEmail($headers);
        $subject = $this->decodeHeader($headers->subject ?? '');
        $user = $this->findUserBySender($senderEmail);

        if (! $user) {
            ImportedMail::query()->create([
                'message_id' => $messageId,
                'sender_email' => $senderEmail,
                'subject' => $subject,
                'status' => 'ignored',
                'notes' => 'Kein TripControl-Benutzer fuer Absender gefunden.',
                'processed_at' => now(),
            ]);

            $this->markSeen($imap, $messageNumber);

            return 'ignored';
        }

        $mail = $this->mailContent($imap, $messageNumber);
        $extracted = $this->extractor->extract($user, $subject, $mail['body'], $mail['attachments']);
        $trip = $this->resolveTrip($user, $extracted);
        $booking = $this->createBooking($trip, $extracted, $subject);

        foreach ($mail['attachments'] as $attachment) {
            $this->storeDocument($trip, $booking, $attachment, $extracted);
        }

        ImportedMail::query()->create([
            'user_id' => $user->id,
            'trip_id' => $trip->id,
            'message_id' => $messageId,
            'sender_email' => $senderEmail,
            'subject' => $subject,
            'status' => 'imported',
            'notes' => $extracted['notes'] ?? null,
            'processed_at' => now(),
        ]);

        if ($booking) {
            $this->regenerateSummary($trip);
        }

        $this->markSeen($imap, $messageNumber);

        return 'imported';
    }

    private function resolveTrip(User $user, array $data): Trip
    {
        $tripId = (int) ($data['trip_id'] ?? 0);

        if ($tripId > 0) {
            $trip = $user->trips()->whereKey($tripId)->first()
                ?? $user->sharedTrips()->whereKey($tripId)->first();

            if ($trip) {
                return $trip;
            }
        }

        $startDate = $this->date($data['trip_start_date'] ?? '') ?: $this->date($data['start_date'] ?? '');

        return $user->trips()->create([
            'title' => $this->clean($data['trip_title'] ?? '') ?: $this->clean($data['title'] ?? '') ?: 'Importierte Reise',
            'type' => in_array($data['trip_type'] ?? '', Trip::TYPES, true) ? $data['trip_type'] : 'other',
            'destination' => $this->clean($data['trip_destination'] ?? '') ?: null,
            'start_date' => $startDate,
            'end_date' => $this->date($data['trip_end_date'] ?? '') ?: $startDate,
            'status' => in_array($data['trip_status'] ?? '', Trip::STATUSES, true) ? $data['trip_status'] : 'planned',
            'notes' => $this->clean($data['trip_notes'] ?? '') ?: 'Automatisch aus weitergeleiteter Mail erstellt.',
        ]);
    }

    private function createBooking(Trip $trip, array $data, string $subject): ?Booking
    {
        if (! ($data['create_booking'] ?? true)) {
            return null;
        }

        $startDate = $this->date($data['start_date'] ?? '') ?: $trip->start_date?->toDateString();
        $validator = Validator::make([
            'category' => in_array($data['category'] ?? '', Booking::CATEGORIES, true) ? $data['category'] : 'other',
            'title' => $this->clean($data['title'] ?? '') ?: $subject ?: 'Importierte Buchung',
            'provider' => $this->clean($data['provider'] ?? '') ?: null,
            'booking_reference' => $this->clean($data['booking_reference'] ?? '') ?: null,
            'amount' => max(0, (float) ($data['amount'] ?? 0)),
            'currency' => in_array($data['currency'] ?? '', Booking::CURRENCIES, true) ? $data['currency'] : 'CHF',
            'start_date' => $startDate,
            'end_date' => $this->date($data['end_date'] ?? '') ?: $startDate,
            'booking_status' => in_array($data['booking_status'] ?? '', Booking::BOOKING_STATUSES, true) ? $data['booking_status'] : 'confirmed',
            'payment_status' => in_array($data['payment_status'] ?? '', Booking::PAYMENT_STATUSES, true) ? $data['payment_status'] : 'unpaid',
            'due_date' => $this->date($data['due_date'] ?? ''),
            'cancellation_deadline' => $this->date($data['cancellation_deadline'] ?? ''),
            'notes' => $this->clean($data['notes'] ?? '') ?: 'Automatisch aus weitergeleiteter Mail importiert.',
        ], $this->bookingRules());

        if ($validator->fails()) {
            throw new RuntimeException('Die AI-Auswertung konnte nicht in eine gueltige Buchung umgewandelt werden.');
        }

        return $trip->bookings()->create($validator->validated());
    }

    private function storeDocument(Trip $trip, ?Booking $booking, array $attachment, array $data): void
    {
        $filename = $attachment['filename'] ?? 'mail-anhang';
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $path = 'documents/'.$trip->id.'/'.Str::random(40).($extension ? ".{$extension}" : '');

        Storage::disk('local')->put($path, $attachment['contents']);

        $trip->documents()->create([
            'booking_id' => $booking?->id,
            'title' => $this->clean($data['document_title'] ?? '') ?: pathinfo($filename, PATHINFO_FILENAME) ?: 'Mail-Anhang',
            'file_path' => $path,
            'document_type' => in_array($data['document_type'] ?? '', Document::TYPES, true) ? $data['document_type'] : 'confirmation',
            'notes' => 'Automatisch aus weitergeleiteter Mail gespeichert.',
        ]);
    }

    private function findUserBySender(string $email): ?User
    {
        $email = Str::lower($email);

        return User::query()->where('email', $email)->first()
            ?? UserEmailAlias::query()->where('email', $email)->first()?->user;
    }

    private function mailContent($imap, int $messageNumber): array
    {
        $structure = imap_fetchstructure($imap, $messageNumber);
        $result = ['body' => '', 'attachments' => []];

        if ($structure) {
            $this->collectPart($imap, $messageNumber, $structure, '', $result);
        }

        return [
            'body' => trim($result['body']),
            'attachments' => $result['attachments'],
        ];
    }

    private function collectPart($imap, int $messageNumber, object $part, string $partNumber, array &$result): void
    {
        if (($part->type ?? null) === 1 && isset($part->parts)) {
            foreach ($part->parts as $index => $subPart) {
                $this->collectPart($imap, $messageNumber, $subPart, $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1), $result);
            }

            return;
        }

        $rawBody = $partNumber === ''
            ? imap_body($imap, $messageNumber)
            : imap_fetchbody($imap, $messageNumber, $partNumber);
        $body = $this->decodeBody((string) $rawBody, (int) ($part->encoding ?? 0));
        $filename = $this->partFilename($part);

        if ($filename) {
            if (! $this->isSupportedAttachment($filename)) {
                return;
            }

            $result['attachments'][] = [
                'filename' => $filename,
                'mime_type' => $this->mimeType($part),
                'contents' => $body,
            ];

            return;
        }

        if (($part->type ?? null) === 0) {
            $text = Str::lower($part->subtype ?? '') === 'html' ? trim(strip_tags($body)) : trim($body);
            $result['body'] .= "\n".$text;
        }
    }

    private function partFilename(object $part): ?string
    {
        foreach (['dparameters', 'parameters'] as $property) {
            foreach (($part->{$property} ?? []) as $parameter) {
                if (in_array(Str::lower($parameter->attribute ?? ''), ['filename', 'name'], true)) {
                    return $this->decodeHeader($parameter->value ?? '');
                }
            }
        }

        return null;
    }

    private function isSupportedAttachment(string $filename): bool
    {
        return in_array(Str::lower(pathinfo($filename, PATHINFO_EXTENSION)), ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true);
    }

    private function mimeType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $types[(int) ($part->type ?? 7)] ?? 'application';
        $subtype = Str::lower($part->subtype ?? 'octet-stream');

        return "{$type}/{$subtype}";
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body, true) ?: '',
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function senderEmail(object $headers): string
    {
        $from = $headers->from[0] ?? null;

        if (! $from || empty($from->mailbox) || empty($from->host)) {
            return '';
        }

        return Str::lower($from->mailbox.'@'.$from->host);
    }

    private function messageId($imap, int $messageNumber): string
    {
        $headers = (string) imap_fetchheader($imap, $messageNumber);

        if (preg_match('/^Message-ID:\s*(.+)$/mi', $headers, $matches)) {
            return trim($matches[1]);
        }

        return 'uid:'.imap_uid($imap, $messageNumber);
    }

    private function decodeHeader(string $value): string
    {
        $decoded = '';

        foreach (imap_mime_header_decode($value) ?: [] as $part) {
            $decoded .= $part->text;
        }

        return trim($decoded ?: $value);
    }

    private function markSeen($imap, int $messageNumber): void
    {
        if (config('mail_import.mark_seen')) {
            imap_setflag_full($imap, (string) $messageNumber, '\\Seen');
        }
    }

    private function regenerateSummary(Trip $trip): void
    {
        try {
            $this->summaryGenerator->regenerate($trip->refresh());
        } catch (Throwable) {
            // Mail import remains successful even if summary generation fails.
        }
    }

    private function bookingRules(): array
    {
        return [
            'category' => ['required', Rule::in(Booking::CATEGORIES)],
            'title' => ['required', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'booking_reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::in(Booking::CURRENCIES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'booking_status' => ['required', Rule::in(Booking::BOOKING_STATUSES)],
            'payment_status' => ['required', Rule::in(Booking::PAYMENT_STATUSES)],
            'due_date' => ['nullable', 'date'],
            'cancellation_deadline' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function clean(mixed $value): string
    {
        return trim((string) $value);
    }

    private function date(mixed $value): ?string
    {
        $value = $this->clean($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
