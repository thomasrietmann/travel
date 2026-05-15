<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripRequest;
use App\Models\Trip;
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
        return view('trips.create', ['trip' => new Trip()]);
    }

    public function store(TripRequest $request): RedirectResponse
    {
        $trip = $request->user()->trips()->create($request->validated());

        return redirect()->route('trips.show', $trip)->with('status', 'Reise wurde erstellt.');
    }

    public function show(Trip $trip): View
    {
        $this->authorize('view', $trip);

        $trip->load([
            'bookings' => fn ($query) => $query
                ->orderByRaw('start_date is null')
                ->orderBy('start_date')
                ->orderByRaw("case category when 'flight' then 0 when 'car' then 1 when 'hotel' then 2 else 3 end")
                ->orderBy('title'),
            'bookings.documents',
            'tasks',
            'documents.booking',
            'user',
            'sharedUsers',
        ]);

        return view('trips.show', ['trip' => $trip]);
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

    public function destroy(Trip $trip): RedirectResponse
    {
        $this->authorize('delete', $trip);

        $trip->delete();

        return redirect()->route('dashboard')->with('status', 'Reise wurde geloescht.');
    }
}
