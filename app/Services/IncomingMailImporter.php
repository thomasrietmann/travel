<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Document;
use App\Models\ImportedMail;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IncomingMailImporter
{
    public function __construct(
        private readonly MailAiExtractor $extractor,
        private readonly TripSummaryGenerator $summaryGenerator,
        private readonly DocumentStorage $documentStorage,
        private readonly MailImportLogger $logger,
        private readonly ImapMailbox $mailbox,
        private readonly MailContentReader $reader,
        private readonly TrustedMailSender $senders,
        private readonly EmailIdentity $identities,
        private readonly ProcessLock $lock,
    ) {}

    public function import(int $limit = 0): array
    {
        if (! config('mail_import.enabled') || ! config('mail_import.authentication_results_trusted') || ! config('mail_import.trusted_authserv_ids')) {
            throw new RuntimeException('Mailimport benötigt eine geprüfte Absenderauthentifizierung.');
        }

        return $this->lock->run('mail-import-service', function () use ($limit): array {
            $this->mailbox->open();
            $stats = ['imported' => 0, 'ignored' => 0, 'failed' => 0, 'deferred' => 0];
            try {
                foreach ($this->mailbox->messages(1000) as $number) {
                    try {
                        $stats[$this->importMessage((int) $number)]++;
                    } catch (Throwable $exception) {
                        $this->logger->error('Mail konnte nicht verarbeitet werden.', ['message_number' => $number, 'exception_class' => $exception::class]);
                        $stats['failed']++;
                    }
                    if (max(1, min(10, $limit ?: config('mail_import.max_messages', 10))) <= $stats['imported'] + $stats['failed']) {
                        break;
                    }
                }
            } finally {
                $this->mailbox->close();
            }

            return $stats;
        });
    }

    private function importMessage(int $number): string
    {
        $headers = $this->mailbox->headers($number);
        $raw = $this->mailbox->rawHeaders($number);
        $key = $this->mailbox->key($number);
        $sender = $this->senders->address($headers, $raw);
        $existing = ImportedMail::query()->where('message_id', $key)->first();
        // Preserve deduplication of messages already committed by the previous release.
        if (! $existing && preg_match('/^Message-ID:\s*(.+)$/mi', $raw, $match)) {
            $existing = ImportedMail::query()->where('message_id', trim($match[1]))
                ->where('sender_email', $sender ?? '')->where('status', 'imported')->first();
        }
        if ($existing?->status === 'imported') {
            $this->mailbox->markSeen($number);

            return 'ignored';
        }
        if ($existing && ($existing->attempts >= 3 || (in_array($existing->status, ['failed', 'blocked'], true) && $existing->updated_at->gt(now()->subMinutes(15))))) {
            return 'deferred';
        }
        $user = $sender ? $this->identities->verifiedUser($sender) : null;
        $subject = Str::limit(mb_decode_mimeheader((string) ($headers->subject ?? '')), 230, '');
        $record = $existing ?? new ImportedMail(['message_id' => $key]);
        $record->fill([
            'sender_email' => $sender ?? '', 'subject' => $subject, 'user_id' => $user?->id,
            'status' => $user ? 'processing' : 'blocked',
            'notes' => $user ? 'Mail wird verarbeitet.' : 'Absenderauthentifizierung oder Adressbestätigung fehlt.',
        ])->save();
        if (! $user) {
            // Leave unread so ownership/configuration can be corrected without losing mail.
            return 'failed';
        }
        $record->increment('attempts');
        try {
            if ((int) ($headers->Size ?? 0) <= 0 || (int) $headers->Size > 25 * 1024 * 1024) {
                throw new RuntimeException('Mailgrösse ist ungültig.');
            }
            $mail = $this->reader->read($number);
            $data = $this->extractor->extract($user, $subject, $mail['body'], $mail['attachments']);
            $trip = $this->documentStorage->transaction(function () use ($user, $data, $subject, $mail, $record): Trip {
                $trip = $this->resolveTrip($user, $data);
                $booking = $this->createBooking($trip, $data, $subject);
                foreach ($mail['attachments'] as $attachment) {
                    $this->storeDocument($trip, $booking, $attachment, $data);
                }
                if (! $this->hasPdfAttachment($mail['attachments'])) {
                    $this->storeMailPdfDocument($trip, $booking, $subject, $mail);
                }
                $record->update(['trip_id' => $trip->id, 'status' => 'imported', 'notes' => null, 'processed_at' => now()]);

                return $trip;
            });
        } catch (Throwable $exception) {
            // A lost commit acknowledgement must never turn committed data into a retry.
            if ($record->fresh()?->status === 'imported') {
                return 'imported';
            }
            $record->update(['status' => 'failed', 'notes' => 'Verarbeitung fehlgeschlagen; Wiederholung möglich.', 'processed_at' => now()]);
            $this->logger->error('Mailverarbeitung fehlgeschlagen.', ['imported_mail_id' => $record->id, 'exception_class' => $exception::class]);

            return 'failed';
        }
        // These operations must never turn a committed import into a retryable failure.
        $this->regenerateSummary($trip);
        $this->logger->info('Mail importiert.', ['imported_mail_id' => $record->id, 'user_id' => $user->id, 'trip_id' => $trip->id]);
        $this->mailbox->markSeen($number);

        return 'imported';
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

        $startDate = BookingData::date($data['trip_start_date'] ?? '') ?: $bookingStartDate;
        $endDate = BookingData::date($data['trip_end_date'] ?? '') ?: $bookingEndDate ?: $startDate;

        return $user->trips()->create([
            'title' => BookingData::clean($data['trip_title'] ?? '') ?: BookingData::clean($data['title'] ?? '') ?: 'Importierte Reise',
            'type' => in_array($data['trip_type'] ?? '', Trip::TYPES, true) ? $data['trip_type'] : 'other',
            'destination' => BookingData::clean($data['trip_destination'] ?? '') ?: null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => in_array($data['trip_status'] ?? '', Trip::STATUSES, true) ? $data['trip_status'] : 'planned',
            'notes' => BookingData::clean($data['trip_notes'] ?? '') ?: 'Automatisch aus weitergeleiteter Mail erstellt.',
        ]);
    }

    private function bookingDateRange(array $data): array
    {
        $startDate = BookingData::date($data['start_date'] ?? '')
            ?: BookingData::date($data['trip_start_date'] ?? '');
        $endDate = BookingData::date($data['end_date'] ?? '')
            ?: BookingData::date($data['trip_end_date'] ?? '')
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

        $data['title'] = BookingData::clean($data['title'] ?? '') ?: $subject;
        $data['notes'] = BookingData::clean($data['notes'] ?? '') ?: 'Automatisch aus weitergeleiteter Mail importiert.';
        $validator = Validator::make(BookingData::normalize($data, $trip), BookingData::rules());

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
            'title' => BookingData::clean($data['document_title'] ?? '') ?: pathinfo($filename, PATHINFO_FILENAME) ?: 'Mail-Anhang',
            'file_path' => $path,
            'document_type' => in_array($data['document_type'] ?? '', Document::TYPES, true) ? $data['document_type'] : 'confirmation',
            'notes' => 'Automatisch aus weitergeleiteter Mail gespeichert.',
        ]);
    }

    private function storeMailPdfDocument(Trip $trip, ?Booking $booking, string $subject, array $mail): void
    {
        $path = 'documents/'.$trip->id.'/'.Str::random(40).'.pdf';
        $this->writeDocumentFile($path, app(MailPdfRenderer::class)->render($subject, $mail['body']));

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

    private function regenerateSummary(Trip $trip): void
    {
        try {
            $this->summaryGenerator->regenerate($trip->refresh());
        } catch (Throwable) {
            // Mail import remains successful even if summary generation fails.
        }
    }
}
