<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $trips = $request->user()
            ->trips()
            ->with(['bookings', 'tasks', 'documents'])
            ->latest('start_date')
            ->get()
            ->sortBy(fn ($trip) => sprintf(
                '%d-%d-%012d',
                $trip->is_active ? 0 : 1,
                $trip->is_past ? 1 : 0,
                $trip->start_date?->timestamp ?? PHP_INT_MAX,
            ))
            ->values();

        return view('dashboard.index', [
            'trips' => $trips,
        ]);
    }
}
