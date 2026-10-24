<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $trips = Trip::query()
            ->with(['bookings', 'tasks', 'documents'])
            ->visibleTo($request->user())
            ->latest('start_date')
            ->get()
            ->sortBy(function (Trip $trip): string {
                $timestamp = $trip->start_date?->timestamp ?? PHP_INT_MAX;
                $dateSort = $trip->is_past ? PHP_INT_MAX - $timestamp : $timestamp;

                return sprintf(
                    '%d-%d-%012d',
                    $trip->is_active ? 0 : 1,
                    $trip->is_past ? 1 : 0,
                    $dateSort,
                );
            })
            ->values();

        return view('dashboard.index', [
            'trips' => $trips,
        ]);
    }
}
