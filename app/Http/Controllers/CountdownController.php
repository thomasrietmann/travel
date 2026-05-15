<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CountdownController extends Controller
{
    public function __invoke(Request $request): View
    {
        $trips = Trip::query()
            ->with(['bookings', 'tasks'])
            ->where(fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->orWhereHas('sharedUsers', fn ($sharedQuery) => $sharedQuery->whereKey($request->user()->id)))
            ->whereDate('start_date', '>', today())
            ->orderBy('start_date')
            ->get();

        return view('countdown.index', [
            'trips' => $trips,
        ]);
    }
}
