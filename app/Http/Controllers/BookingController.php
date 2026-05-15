<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use App\Services\TripSummaryGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class BookingController extends Controller
{
    public function create(Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('bookings.create', [
            'trip' => $trip,
            'booking' => new Booking([
                'start_date' => $trip->start_date,
                'end_date' => $trip->start_date,
            ]),
        ]);
    }

    public function store(BookingRequest $request, Trip $trip, TripSummaryGenerator $summaryGenerator): RedirectResponse
    {
        $this->authorize('update', $trip);

        $booking = $trip->bookings()->create($this->bookingData($request));
        $this->storeDocument($request, $trip, $booking);
        $this->regenerateSummary($summaryGenerator, $trip);

        return redirect()->route('trips.show', $trip)->with('status', 'Buchung wurde erstellt.');
    }

    public function edit(Booking $booking): View
    {
        $this->authorize('update', $booking);

        return view('bookings.edit', [
            'trip' => $booking->trip,
            'booking' => $booking->load('documents'),
        ]);
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $booking->update($this->bookingData($request));
        $this->storeDocument($request, $booking->trip, $booking);

        return redirect()->route('trips.show', $booking->trip)->with('status', 'Buchung wurde aktualisiert.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        $trip = $booking->trip;
        $booking->delete();

        return redirect()->route('trips.show', $trip)->with('status', 'Buchung wurde geloescht.');
    }

    private function bookingData(BookingRequest $request): array
    {
        return collect($request->validated())
            ->except(['document_title', 'document_type', 'document_file', 'document_notes'])
            ->all();
    }

    private function storeDocument(BookingRequest $request, Trip $trip, Booking $booking): void
    {
        if (! $request->hasFile('document_file')) {
            return;
        }

        $trip->documents()->create([
            'booking_id' => $booking->id,
            'title' => $request->input('document_title') ?: $booking->title,
            'file_path' => $request->file('document_file')->store("documents/{$trip->id}", 'public'),
            'document_type' => $request->input('document_type', 'confirmation'),
            'notes' => $request->input('document_notes'),
        ]);
    }

    private function regenerateSummary(TripSummaryGenerator $summaryGenerator, Trip $trip): void
    {
        try {
            $summaryGenerator->regenerate($trip->refresh());
        } catch (Throwable) {
            // Eine Buchung soll nicht scheitern, nur weil die AI-Summary nicht erstellt werden konnte.
        }
    }
}
