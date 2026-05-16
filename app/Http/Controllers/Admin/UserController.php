<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    public function show(User $user): View
    {
        $user->load([
            'trips' => fn ($query) => $query
                ->withCount(['bookings', 'tasks', 'documents'])
                ->latest('start_date'),
            'emailAliases',
        ])->loadCount(['trips', 'bookings']);

        return view('admin.users.show', [
            'managedUser' => $user,
        ]);
    }
}
