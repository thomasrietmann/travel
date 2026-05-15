<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingImportRequest;
use App\Models\Booking;
use App\Models\Document;
use App\Models\Trip;
use App\Services\BookingAiExtractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class BookingImportController extends Controller
{
    public function store(BookingImportRequest $request, Trip $trip, BookingAiExtractor $extractor): RedirectResponse
    {
        $this->authorize('update', $trip);

        try {
            $extracted = $extractor->extract($trip, $request->file('booking_document'));
        } catch (Throwable $exception) {
            return back()
                ->withInput()
                ->withErrors(['booking_document' => $exception->getMessage()]);
        }

        $bookingData = $this->bookingData($extracted, $trip);
        $validator = Validator::make($bookingData, [
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
        ]);

        if ($validator->fails()) {
            return back()
                ->withInput()
                ->withErrors(['booking_document' => 'Die AI-Auswertung konnte nicht in eine gueltige Buchung umgewandelt werden.']);
        }

        $booking = $trip->bookings()->create($validator->validated());
        $trip->documents()->create([
            'booking_id' => $booking->id,
            'title' => $this->clean($extracted['document_title'] ?? '') ?: $booking->title,
            'file_path' => $request->file('booking_document')->store("documents/{$trip->id}", 'public'),
            'document_type' => in_array($extracted['document_type'] ?? '', Document::TYPES, true) ? $extracted['document_type'] : 'confirmation',
            'notes' => 'Automatisch aus Buchungsimport erstellt.',
        ]);

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', 'Buchung wurde per AI-Import erstellt. Bitte pruefe die erkannten Daten kurz.');
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
