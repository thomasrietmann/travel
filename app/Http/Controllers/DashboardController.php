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
            ->get();

        return view('dashboard.index', [
            'trips' => $trips,
        ]);
    }
}
