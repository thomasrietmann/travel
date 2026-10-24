<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, EmailIdentity $identities): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email', 'unique:user_email_aliases,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $identities->create($validated['email'], fn (string $email) => User::query()->create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
        ]));

        // Ownership verification is explicitly requested in Settings, so SMTP outages do not break registration.

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
