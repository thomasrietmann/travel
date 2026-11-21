<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Trip;
use App\Services\DocumentStorage;
use App\Services\TripItinerary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('dashboard');
    }

    public function create(): View
    {
        return view('trips.create', ['trip' => new Trip]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = $request->user()->trips()->create($request->validated());

        return redirect()->route('trips.show', $trip)->with('status', 'Reise wurde erstellt.');
    }

    public function show(Request $request, Trip $trip, TripItinerary $itinerary): View
    {
        $this->authorize('view', $trip);

        $trip->load([
            'bookings' => fn ($query) => $query
                ->orderByRaw('start_date is null')
                ->orderBy('start_date')
                ->orderByRaw("case category when 'flight' then 0 when 'car' then 1 when 'hotel' then 2 else 3 end")
                ->orderBy('title'),
            'bookings.documents',
            'stops',
            'tasks',
            'documents.booking',
            'user',
            'sharedUsers',
        ]);

        $flightsOnly = $request->query('plan') === 'fluege';
        $plan = $itinerary->forTrip($trip, $flightsOnly);
        $dates = $plan['days']->keys();
        $requestedDay = $request->query('tag');
        $today = today()->toDateString();
        $openDay = $dates->containsStrict($requestedDay)
            ? $requestedDay
            : ($dates->first(fn ($date) => $date >= $today) ?? $dates->first());

        return view('trips.show', compact('trip', 'plan', 'flightsOnly', 'openDay'));
    }

    public function edit(Trip $trip): View
    {
        $this->authorize('update', $trip);

        return view('trips.edit', ['trip' => $trip]);
    }

    public function update(TripRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $trip->update($request->validated());

        return redirect()->route('trips.show', $trip)->with('status', 'Reise wurde aktualisiert.');
    }

    public function destroy(Trip $trip, DocumentStorage $storage): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $storage->transaction(function () use ($trip, $storage): void {
            $current = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            foreach ($current->documents()->get() as $document) {
                $storage->scheduleDeletion($document->file_path);
            }
            $current->delete();
        });

        return redirect()->route('dashboard')->with('status', 'Reise wurde geloescht.');
    }
}
