<?php

namespace App\Http\Controllers;

use App\Http\Requests\ItineraryStopRequest;
use App\Models\ItineraryStop;
use App\Models\Trip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ItineraryStopController extends Controller
{
    public function create(Request $request, Trip $trip): View
    {
        $this->authorize('update', $trip);
        $booking = $request->filled('booking') ? $trip->bookings()->findOrFail($request->integer('booking')) : null;
        $start = $booking?->start_date ?? $trip->start_date;
        if (in_array($booking?->category, ['ticket', 'insurance'], true) && $booking->end_date && $start && $trip->start_date?->betweenIncluded($start, $booking->end_date)) {
            $start = $trip->start_date;
        }
        $stop = new ItineraryStop([
            'title' => $booking?->title,
            'start_date' => $start,
            'end_date' => in_array($booking?->category, ['hotel', 'car', 'camper'], true) ? $booking->end_date : null,
        ]);

        return view('itinerary.form', compact('trip', 'booking', 'stop'));
    }

    public function store(ItineraryStopRequest $request, Trip $trip): RedirectResponse
    {
        $stop = $trip->stops()->create($request->validated());

        return $this->backToDay($trip, $stop, 'Eintrag zum Tagesplan hinzugefügt.');
    }

    public function edit(Trip $trip, ItineraryStop $stop): View
    {
        $this->authorize('update', $trip);

        return view('itinerary.form', ['trip' => $trip, 'stop' => $stop, 'booking' => $stop->booking]);
    }

    public function update(ItineraryStopRequest $request, Trip $trip, ItineraryStop $stop): RedirectResponse
    {
        $stop->update($request->safe()->except('booking_id'));

        return $this->backToDay($trip, $stop, 'Eintrag aktualisiert.');
    }

    public function destroy(Trip $trip, ItineraryStop $stop): RedirectResponse
    {
        $this->authorize('update', $trip);
        $stop->delete();

        return $this->backToDay($trip, $stop, 'Eintrag aus dem Tagesplan entfernt.');
    }

    private function backToDay(Trip $trip, ItineraryStop $stop, string $message): RedirectResponse
    {
        return redirect()->to(route('trips.show', [$trip, 'tag' => $stop->start_date->toDateString()]).'#tagesplan')
            ->with('status', $message);
    }
}
