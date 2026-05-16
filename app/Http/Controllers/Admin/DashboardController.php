<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $users = User::query()
            ->withCount(['trips', 'bookings'])
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', [
            'users' => $users,
        ]);
    }
}
