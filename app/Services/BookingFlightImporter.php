<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingFlightExtraction;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BookingFlightImporter
{
    public function __construct(private readonly DocumentStorage $storage) {}

    public static function schema(): array
    {
        $properties = [];
        foreach (array_keys(ItineraryStopData::rules()) as $field) {
            $properties[$field] = ['type' => $field === 'title' ? 'string' : ['string', 'null']];
        }

        return [
            'type' => 'array',
            'maxItems' => 50,
            'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'properties' => $properties, 'required' => array_keys($properties),
            ],
        ];
    }

    public static function prompt(): string
    {
        return implode("\n", [
            'Liefere fuer Flugbuchungen zusaetzlich flight_segments mit ALLEN einzelnen Flugstrecken, einschliesslich Hinflug, Rueckflug und Umstiegen.',
            'Jede Teilstrecke hat einen eigenen Eintrag. Derselbe Flug fuer mehrere Reisende kommt nur einmal vor.',
            'title ist die Flugnummer, z. B. LX 18, ersatzweise die Strecke. Flughafencodes in start_location und end_location verwenden, sofern erkennbar.',
            'start_date/end_date sind die tatsaechlichen lokalen Abflug-/Ankunftsdaten YYYY-MM-DD, start_time/end_time lokale Uhrzeiten HH:MM.',
            'Uebernachtfluege, Zeitverschiebungen und Datumsgrenzen beachten. Ortszeiten unveraendert lassen.',
            'start_timezone/end_timezone als IANA-Zeitzonen wie Europe/Zurich oder America/New_York aus eindeutig erkannten Flughaefen ableiten.',
            'Unbekannte Daten, Uhrzeiten, Orte und Zeitzonen als null liefern. Fuer Teilstrecken keine Reisedaten oder Buchungsdaten als Flugdaten erfinden.',
            'Nur Teilstrecken mit erkennbarer Flugstrecke und konkretem Abflugdatum aufnehmen. Ohne solche Angaben oder bei anderen Buchungskategorien flight_segments als [] liefern.',
        ]);
    }

    /** Reuse the first import response for every attached source document. */
    public function remember(Booking $booking, array $data): void
    {
        if ($booking->category !== 'flight' || ! array_key_exists('flight_segments', $data)) {
            return;
        }
        foreach ($booking->documents()->get() as $document) {
            $path = $this->storage->locate($document->file_path);
            if ($path === null) {
                throw new RuntimeException('Buchungsdokument wurde nicht gefunden.');
            }
            $record = $booking->flightExtractions()->firstOrCreate(
                ['content_hash' => $this->storage->checksum($path)],
                ['document_id' => $document->id, 'document_path' => $document->file_path,
                    'status' => 'extracted', 'segments' => $data['flight_segments']],
            );
            $this->apply($booking, $record);
        }
    }

    public function extract(Booking $booking, bool $retryFailed = false): array
    {
        return app(ProcessLock::class)->run('booking-flights-'.$booking->id, function () use ($booking, $retryFailed): array {
            $stats = ['created' => 0, 'cached' => 0, 'failed' => 0, 'review' => 0, 'documents' => 0];
            foreach ($booking->documents()->whereNotIn('document_type', ['passport', 'insurance'])->orderBy('id')->get() as $document) {
                $stats['documents']++;
                try {
                    $path = $this->storage->locate($document->file_path);
                    if ($path === null || filesize($path) > 10 * 1024 * 1024) {
                        throw new RuntimeException('Buchungsdokument fehlt oder ist zu gross.');
                    }
                    $hash = $this->storage->checksum($path);
                    $record = $booking->flightExtractions()->where('content_hash', $hash)->first();
                    if (in_array($record?->status, ['completed', 'review'], true)) {
                        $stats[$record->status === 'review' ? 'review' : 'cached']++;

                        continue;
                    }
                    if ($record?->status === 'processing' || ($record?->status === 'failed' && ! $retryFailed)) {
                        $stats['failed']++;

                        continue;
                    }
                    if ($record?->status !== 'extracted') {
                        $file = new UploadedFile($path, basename($path), null, null, true);
                        if (! in_array($file->getMimeType(), ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                            throw new RuntimeException('Nicht unterstützter Dateityp.');
                        }
                        $record = $booking->flightExtractions()->updateOrCreate(
                            ['content_hash' => $hash],
                            ['document_id' => $document->id, 'document_path' => $document->file_path, 'status' => 'processing'],
                        );
                        try {
                            $data = app(BookingAiExtractor::class)->extract($booking->trip, $file, $booking);
                        } catch (Throwable $exception) {
                            $record->update(['status' => 'failed']);
                            throw $exception;
                        }
                        // Persist the response before applying it. A retry can resume without another API call.
                        $record->update(['status' => 'extracted', 'segments' => $data['flight_segments'] ?? []]);
                    }
                    $stats['created'] += DB::transaction(function () use ($booking, $record, $document): int {
                        $current = Booking::query()->lockForUpdate()->findOrFail($booking->id);
                        $source = Document::query()->lockForUpdate()->find($document->id);
                        if ($source?->booking_id !== $current->id || $source->file_path !== $record->document_path) {
                            $record->update(['status' => 'review']);

                            return 0;
                        }

                        return $this->apply($current, $record);
                    });
                    $stats['review'] += (int) ($record->status === 'review');
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    Log::warning('Flugstrecken konnten nicht übernommen werden.', ['booking_id' => $booking->id, 'document_id' => $document->id, 'exception_class' => $exception::class]);
                }
            }

            return $stats;
        });
    }

    private function apply(Booking $booking, BookingFlightExtraction $record): int
    {
        if (in_array($record->status, ['completed', 'review'], true)) {
            return 0;
        }
        try {
            $data = Validator::make(['segments' => $record->segments], ['segments' => ['present', 'array', 'max:50']])->validate();
            $segments = [];
            foreach ($data['segments'] as $segment) {
                if (! is_array($segment)) {
                    throw ValidationException::withMessages(['segments' => 'Ungültige Teilstrecke.']);
                }
                $values = [];
                foreach (array_keys(ItineraryStopData::rules()) as $field) {
                    $values[$field] = BookingData::clean($segment[$field] ?? '') ?: null;
                }
                $validator = Validator::make($values, ItineraryStopData::rules());
                $validator->after(ItineraryStopData::validateEnd(...));
                $segments[] = $validator->validate();
            }
        } catch (ValidationException) {
            $record->update(['status' => 'review']);

            return 0;
        }
        $created = 0;
        foreach ($segments as $segment) {
            $identity = Arr::except($segment, ['title', 'start_timezone', 'end_timezone']);
            if (blank($segment['start_time']) || blank($segment['start_location']) || blank($segment['end_location'])) {
                $identity['title'] = $segment['title'];
            }
            $existing = $booking->stops();
            foreach ($identity as $field => $value) {
                if ($value !== null && str_ends_with($field, '_date')) {
                    $existing->whereDate($field, $value);
                } elseif ($value !== null && str_ends_with($field, '_time')) {
                    $existing->whereTime($field, $value.':00');
                } else {
                    $existing->where($field, $value);
                }
            }
            if (! $existing->exists()) {
                $booking->trip->stops()->create(['booking_id' => $booking->id, ...$segment]);
                $created++;
            }
        }
        $record->update(['status' => 'completed']);

        return $created;
    }
}
