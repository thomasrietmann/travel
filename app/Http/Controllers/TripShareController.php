<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShareTripRequest;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TripShareController extends Controller
{
    public function store(ShareTripRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('share', $trip);

        $user = User::query()
            ->where('email', $request->validated('email'))
            ->firstOrFail();

        if ($trip->isOwnedBy($user)) {
            return back()->withErrors(['email' => 'Der Besitzer hat bereits Zugriff.']);
        }

        $trip->sharedUsers()->syncWithoutDetaching([$user->id]);

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', 'Reise wurde geteilt.');
    }

    public function destroy(Trip $trip, User $user): RedirectResponse
    {
        $this->authorize('share', $trip);

        $trip->sharedUsers()->detach($user->id);

        return redirect()
            ->route('trips.show', $trip)
            ->with('status', 'Freigabe wurde entfernt.');
    }
}
