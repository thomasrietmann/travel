<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingImportRequest;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Services\BookingAiExtractor;
use App\Services\TripSummaryGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class BookingImportController extends Controller
{
    public function store(BookingImportRequest $request, Trip $trip, BookingAiExtractor $extractor, TripSummaryGenerator $summaryGenerator): RedirectResponse
    {
        $this->authorize('update', $trip);

        $created = 0;
        $failed = [];
        $files = $this->uploadedFiles($request->file('booking_documents', []));

        foreach ($files as $file) {
            try {
                $extracted = $extractor->extract($trip, $file);
                $validator = Validator::make($this->bookingData($extracted, $trip), $this->bookingRules());

                if ($validator->fails()) {
                    $failed[] = $file->getClientOriginalName().': Die AI-Auswertung konnte nicht in eine gueltige Buchung umgewandelt werden.';
                    continue;
                }

                $booking = $trip->bookings()->create($validator->validated());
                $trip->documents()->create([
                    'booking_id' => $booking->id,
                    'title' => $this->clean($extracted['document_title'] ?? '') ?: $booking->title,
                    'file_path' => $file->store("documents/{$trip->id}", 'public'),
                    'document_type' => in_array($extracted['document_type'] ?? '', Document::TYPES, true) ? $extracted['document_type'] : 'confirmation',
                    'notes' => 'Automatisch aus Buchungsimport erstellt.',
                ]);

                $created++;
            } catch (Throwable $exception) {
                $failed[] = $file->getClientOriginalName().': '.$exception->getMessage();
            }
        }

        if ($created === 0) {
            return back()
                ->withInput()
                ->withErrors(['booking_documents' => implode(' ', $failed)]);
        }

        $this->regenerateSummary($summaryGenerator, $trip);

        $message = $created === 1
            ? '1 Buchung wurde per AI-Import erstellt. Bitte pruefe die erkannten Daten kurz.'
            : "{$created} Buchungen wurden per AI-Import erstellt. Bitte pruefe die erkannten Daten kurz.";

        if ($failed !== []) {
            $message .= ' Nicht importiert: '.implode(' ', $failed);
        }

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', $message);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function uploadedFiles(array|UploadedFile|null $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        return array_values(array_filter($files ?? [], fn ($file) => $file instanceof UploadedFile));
    }

    private function regenerateSummary(TripSummaryGenerator $summaryGenerator, Trip $trip): void
    {
        try {
            $summaryGenerator->regenerate($trip->refresh());
        } catch (Throwable) {
            // Erfolgreich importierte Buchungen bleiben bestehen, auch wenn die AI-Summary fehlschlaegt.
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

    private function bookingData(array $data, Trip $trip): array
    {
        $startDate = $this->date($data['start_date'] ?? '') ?: $trip->start_date?->toDateString();

        return [
            'category' => in_array($data['category'] ?? '', Booking::CATEGORIES, true) ? $data['category'] : 'other',
            'title' => $this->clean($data['title'] ?? '') ?: 'Importierte Buchung',
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
            'notes' => $this->clean($data['notes'] ?? '') ?: null,
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
