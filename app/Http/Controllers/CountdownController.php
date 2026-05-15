<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountdownController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('countdown.index', [
            'trips' => $this->upcomingTrips($user),
            'public' => false,
            'shareUrl' => route('countdown.public', $user->ensureCountdownShareToken()),
        ]);
    }

    public function public(string $token): View
    {
        $user = User::query()
            ->where('countdown_share_token', $token)
            ->firstOrFail();

        return view('countdown.index', [
            'trips' => $this->upcomingTrips($user),
            'public' => true,
            'shareUrl' => null,
        ]);
    }

    private function upcomingTrips(User $user)
    {
        return Trip::query()
            ->with(['bookings', 'tasks'])
            ->where(fn ($query) => $query
                ->where('user_id', $user->id)
                ->orWhereHas('sharedUsers', fn ($sharedQuery) => $sharedQuery->whereKey($user->id)))
            ->whereDate('start_date', '>', today())
            ->orderBy('start_date')
            ->get();
    }
}
