<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Document;
use App\Models\ImportedMail;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserEmailAlias;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
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
        private readonly DocumentStorage $documentStorage,
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

        $existingImport = ImportedMail::query()->where('message_id', $messageId)->first();

        if ($existingImport) {
            if ($existingImport->status === 'processing') {
                $existingImport->update([
                    'status' => 'failed',
                    'notes' => 'Vorheriger Mailimport wurde abgebrochen.',
                    'processed_at' => now(),
                ]);

                $this->markSeen($imap, $messageNumber);

                return 'failed';
            }

            $this->markSeen($imap, $messageNumber);

            return 'ignored';
        }

        $senderEmails = $this->senderEmails($headers);
        $senderEmail = $senderEmails[0] ?? '';
        $subject = $this->decodeHeader($headers->subject ?? '');
        $user = $this->findUserBySenders($senderEmails);

        if (! $user) {
            ImportedMail::query()->create([
                'message_id' => $messageId,
                'sender_email' => $senderEmail,
                'subject' => $subject,
                'status' => 'ignored',
                'notes' => 'Kein TripControl-Benutzer fuer Absender gefunden: '.implode(', ', $senderEmails),
                'processed_at' => now(),
            ]);

            $this->markSeen($imap, $messageNumber);

            return 'ignored';
        }

        $importedMail = ImportedMail::query()->create([
            'user_id' => $user->id,
            'message_id' => $messageId,
            'sender_email' => $senderEmail,
            'subject' => $subject,
            'status' => 'processing',
            'notes' => 'Mail wird verarbeitet.',
        ]);

        try {
            $mail = $this->mailContent($imap, $messageNumber);
            $extracted = $this->extractor->extract($user, $subject, $mail['body'], $mail['attachments']);
            $trip = null;
            $booking = null;

            DB::transaction(function () use ($user, $subject, $mail, $extracted, $importedMail, &$trip, &$booking): void {
                $trip = $this->resolveTrip($user, $extracted);
                $booking = $this->createBooking($trip, $extracted, $subject);

                foreach ($mail['attachments'] as $attachment) {
                    $this->storeDocument($trip, $booking, $attachment, $extracted);
                }

                if (! $this->hasPdfAttachment($mail['attachments'])) {
                    $this->storeMailPdfDocument($trip, $booking, $subject, $mail);
                }

                $importedMail->update([
                    'trip_id' => $trip->id,
                    'status' => 'imported',
                    'notes' => $extracted['notes'] ?? null,
                    'processed_at' => now(),
                ]);
            });

            if ($booking) {
                $this->regenerateSummary($trip);
            }

            $this->markSeen($imap, $messageNumber);

            return 'imported';
        } catch (Throwable $exception) {
            $importedMail->update([
                'status' => 'failed',
                'notes' => Str::limit($exception->getMessage(), 2000),
                'processed_at' => now(),
            ]);

            $this->markSeen($imap, $messageNumber);

            return 'failed';
        }
    }

    private function resolveTrip(User $user, array $data): Trip
    {
        [$bookingStartDate, $bookingEndDate] = $this->bookingDateRange($data);

        if ($bookingStartDate) {
            $trip = $this->findTripByDateRange($user, $bookingStartDate, $bookingEndDate ?: $bookingStartDate);

            if ($trip) {
                return $trip;
            }
        }

        $tripId = (int) ($data['trip_id'] ?? 0);

        if ($tripId > 0) {
            $trip = $user->trips()->whereKey($tripId)->first()
                ?? $user->sharedTrips()->whereKey($tripId)->first();

            if ($trip) {
                return $trip;
            }
        }

        $startDate = $this->date($data['trip_start_date'] ?? '') ?: $bookingStartDate;
        $endDate = $this->date($data['trip_end_date'] ?? '') ?: $bookingEndDate ?: $startDate;

        return $user->trips()->create([
            'title' => $this->clean($data['trip_title'] ?? '') ?: $this->clean($data['title'] ?? '') ?: 'Importierte Reise',
            'type' => in_array($data['trip_type'] ?? '', Trip::TYPES, true) ? $data['trip_type'] : 'other',
            'destination' => $this->clean($data['trip_destination'] ?? '') ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => in_array($data['trip_status'] ?? '', Trip::STATUSES, true) ? $data['trip_status'] : 'planned',
            'notes' => $this->clean($data['trip_notes'] ?? '') ?: 'Automatisch aus weitergeleiteter Mail erstellt.',
        ]);
    }

    private function bookingDateRange(array $data): array
    {
        $startDate = $this->date($data['start_date'] ?? '')
            ?: $this->date($data['trip_start_date'] ?? '');
        $endDate = $this->date($data['end_date'] ?? '')
            ?: $this->date($data['trip_end_date'] ?? '')
            ?: $startDate;

        if ($startDate && $endDate && $endDate < $startDate) {
            return [$endDate, $startDate];
        }

        return [$startDate, $endDate];
    }

    private function findTripByDateRange(User $user, string $bookingStartDate, string $bookingEndDate): ?Trip
    {
        $trips = $user->trips()
            ->get(['id', 'user_id', 'title', 'start_date', 'end_date'])
            ->merge($user->sharedTrips()->get([
                'trips.id',
                'trips.user_id',
                'trips.title',
                'trips.start_date',
                'trips.end_date',
            ]))
            ->unique('id')
            ->filter(fn (Trip $trip): bool => (bool) $trip->start_date)
            ->filter(function (Trip $trip) use ($bookingStartDate, $bookingEndDate): bool {
                $tripStartDate = $trip->start_date->toDateString();
                $tripEndDate = $trip->end_date?->toDateString() ?: $tripStartDate;

                return $bookingStartDate <= $tripEndDate && $bookingEndDate >= $tripStartDate;
            });

        return $trips
            ->sortBy(function (Trip $trip) use ($bookingStartDate, $bookingEndDate): string {
                $tripStartDate = $trip->start_date->toDateString();
                $tripEndDate = $trip->end_date?->toDateString() ?: $tripStartDate;
                $containsBooking = $bookingStartDate >= $tripStartDate && $bookingEndDate <= $tripEndDate;

                return sprintf(
                    '%d-%010d-%s',
                    $containsBooking ? 0 : 1,
                    abs(strtotime($tripStartDate) - strtotime($bookingStartDate)),
                    $tripStartDate,
                );
            })
            ->first();
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

        $this->writeDocumentFile($path, $attachment['contents']);

        $trip->documents()->create([
            'booking_id' => $booking?->id,
            'title' => $this->clean($data['document_title'] ?? '') ?: pathinfo($filename, PATHINFO_FILENAME) ?: 'Mail-Anhang',
            'file_path' => $path,
            'document_type' => in_array($data['document_type'] ?? '', Document::TYPES, true) ? $data['document_type'] : 'confirmation',
            'notes' => 'Automatisch aus weitergeleiteter Mail gespeichert.',
        ]);
    }

    private function storeMailPdfDocument(Trip $trip, ?Booking $booking, string $subject, array $mail): void
    {
        $path = 'documents/'.$trip->id.'/'.Str::random(40).'.pdf';
        $this->writeDocumentFile($path, $this->mailPdf($subject, $mail));

        $trip->documents()->create([
            'booking_id' => $booking?->id,
            'title' => $subject ? 'Mail: '.$subject : 'Weitergeleitete Mail',
            'file_path' => $path,
            'document_type' => 'confirmation',
            'notes' => 'Automatisch als PDF aus weitergeleiteter Mail gespeichert.',
        ]);
    }

    private function writeDocumentFile(string $path, string $contents): void
    {
        $this->documentStorage->writePrivate($path, $contents);
    }

    private function hasPdfAttachment(array $attachments): bool
    {
        return collect($attachments)->contains(
            fn (array $attachment): bool => Str::lower(pathinfo($attachment['filename'] ?? '', PATHINFO_EXTENSION)) === 'pdf'
        );
    }

    private function mailPdf(string $subject, array $mail): string
    {
        if (! class_exists(Options::class) || ! class_exists(Dompdf::class)) {
            return $this->fallbackMailPdf($subject, $mail);
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->mailHtml($subject, $mail), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }

    private function fallbackMailPdf(string $subject, array $mail): string
    {
        $body = trim(strip_tags((string) ($mail['html_body'] ?: $mail['body'])));
        $text = implode("\n\n", [
            'TripControl Mailimport',
            'Betreff: '.($subject ?: '-'),
            'Importiert am: '.now()->format('d.m.Y H:i'),
            'Mailinhalt:',
            $body ?: '-',
        ]);

        $lines = $this->fallbackPdfLines($text);
        $pages = array_chunk($lines, 48) ?: [['-']];
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>'];
        $pageObjectIds = [];
        $fontObjectId = 3 + (count($pages) * 2);
        $nextObjectId = 3;

        foreach ($pages as $pageLines) {
            $pageObjectId = $nextObjectId++;
            $contentObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;
            $objects[$pageObjectId] = "<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 {$fontObjectId} 0 R >> >> /MediaBox [0 0 595 842] /Contents {$contentObjectId} 0 R >>";
            $objects[$contentObjectId] = $this->fallbackPdfStream($pageLines);
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageObjectIds)).'] /Count '.count($pageObjectIds).' >>';
        $objects[$fontObjectId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        return $this->fallbackPdfDocument($objects);
    }

    private function fallbackPdfLines(string $text): array
    {
        $lines = [];

        foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $text)) as $line) {
            array_push($lines, ...explode("\n", wordwrap(trim($line), 92, "\n", true) ?: ' '));
        }

        return $lines;
    }

    private function fallbackPdfStream(array $lines): string
    {
        $content = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";

        foreach ($lines as $line) {
            $content .= '('.$this->fallbackPdfEscape($line).") Tj\nT*\n";
        }

        $content .= "ET\n";

        return "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream";
    }

    private function fallbackPdfEscape(string $value): string
    {
        $encoded = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = $encoded === false ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function fallbackPdfDocument(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        for ($id = 1; $id <= count($objects); $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }

    private function mailHtml(string $subject, array $mail): string
    {
        $body = trim((string) ($mail['html_body'] ?: $mail['body']));

        if ($body === '') {
            $body = '<p>-</p>';
        } elseif (! $mail['html_body']) {
            $body = '<pre>'.e($body).'</pre>';
        }

        $body = $this->sanitizeMailHtml($body);
        $header = $this->mailPdfHeader($subject);

        if ($mail['html_body'] && preg_match('/<body\b[^>]*>/i', $body)) {
            return preg_replace('/<body\b[^>]*>/i', '$0'.$header, $body, 1) ?? $body;
        }

        return '<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    '.$this->mailPdfStyles().'
</head>
<body>
    '.$header.'
    '.$body.'
</body>
</html>';
    }

    private function mailPdfHeader(string $subject): string
    {
        return '<div class="tripcontrol-header" style="border-bottom: 1px solid #d1d5db; margin-bottom: 18px; padding-bottom: 10px;">
            <div class="tripcontrol-title" style="font-size: 18px; font-weight: bold; margin-bottom: 6px;">'.e($subject ?: 'Weitergeleitete Mail').'</div>
            <div class="tripcontrol-meta" style="color: #6b7280; font-size: 11px;">TripControl Mailimport · '.e(now()->format('d.m.Y H:i')).'</div>
        </div>';
    }

    private function mailPdfStyles(): string
    {
        return '<style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; line-height: 1.45; }
            .tripcontrol-header { border-bottom: 1px solid #d1d5db; margin-bottom: 18px; padding-bottom: 10px; }
            .tripcontrol-title { font-size: 18px; font-weight: bold; margin-bottom: 6px; }
            .tripcontrol-meta { color: #6b7280; font-size: 11px; }
            pre { white-space: pre-wrap; font-family: DejaVu Sans Mono, monospace; font-size: 11px; }
            table { border-collapse: collapse; max-width: 100%; }
            img { max-width: 100%; height: auto; }
        </style>';
    }

    private function findUserBySenders(array $emails): ?User
    {
        foreach ($emails as $email) {
            $email = Str::lower($email);
            $user = User::query()->where('email', $email)->first()
                ?? UserEmailAlias::query()->where('email', $email)->first()?->user;

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function mailContent($imap, int $messageNumber): array
    {
        $structure = imap_fetchstructure($imap, $messageNumber);
        $result = ['body' => '', 'html_body' => '', 'attachments' => []];

        if ($structure) {
            $this->collectPart($imap, $messageNumber, $structure, '', $result);
        }

        return [
            'body' => trim($result['body']),
            'html_body' => trim($result['html_body']),
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
            if (Str::lower($part->subtype ?? '') === 'html') {
                $result['html_body'] .= "\n".$body;
            }

            $text = Str::lower($part->subtype ?? '') === 'html' ? trim(strip_tags($body)) : trim($body);
            $result['body'] .= "\n".$text;
        }
    }

    private function sanitizeMailHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/<iframe\b[^>]*>.*?<\/iframe>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html) ?? $html;
        $html = preg_replace('/\s(href|src)\s*=\s*(["\'])javascript:.*?\2/is', '', $html) ?? $html;

        return $html;
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

    private function senderEmails(object $headers): array
    {
        $emails = [];

        foreach (['from', 'sender', 'reply_to'] as $property) {
            foreach (($headers->{$property} ?? []) as $address) {
                if (! empty($address->mailbox) && ! empty($address->host)) {
                    $emails[] = Str::lower($address->mailbox.'@'.$address->host);
                }
            }
        }

        return array_values(array_unique($emails));
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
