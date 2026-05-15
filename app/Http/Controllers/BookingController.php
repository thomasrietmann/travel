<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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

    public function store(BookingRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->bookings()->create($request->validated());

        return redirect()->route('trips.show', $trip)->with('status', 'Buchung wurde erstellt.');
    }

    public function edit(Booking $booking): View
    {
        $this->authorize('update', $booking);

        return view('bookings.edit', ['trip' => $booking->trip, 'booking' => $booking]);
    }

    public function update(BookingRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('update', $booking);

        $booking->update($request->validated());

        return redirect()->route('trips.show', $booking->trip)->with('status', 'Buchung wurde aktualisiert.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        $trip = $booking->trip;
        $booking->delete();

        return redirect()->route('trips.show', $trip)->with('status', 'Buchung wurde geloescht.');
    }
}
