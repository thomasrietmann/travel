<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserBirthdayRequest;
use App\Http\Requests\UserEmailAliasRequest;
use App\Models\UserBirthday;
use App\Models\UserEmailAlias;
use App\Services\EmailIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $user = request()->user()->load(['emailAliases', 'birthdays' => fn ($query) => $query->orderBy('name')]);

        return view('settings.index', [
            'user' => $user,
            'shareUrl' => $user->countdown_share_token ? route('countdown.public', $user->countdown_share_token) : null,
        ]);
    }

    public function storeEmailAlias(UserEmailAliasRequest $request, EmailIdentity $identities): RedirectResponse
    {
        $identities->create($request->validated('email'), fn (string $email) => $request->user()->emailAliases()->create(['email' => $email]));

        return back()->with('status', 'E-Mail-Adresse wurde hinzugefügt. Bitte fordere jetzt die Bestätigungsmail an.');
    }

    public function destroyEmailAlias(UserEmailAlias $alias): RedirectResponse
    {
        abort_unless($alias->user_id === request()->user()->id, 403);

        $alias->delete();

        return back()->with('status', 'E-Mail-Adresse wurde entfernt.');
    }

    public function rotateCountdown(): RedirectResponse
    {
        request()->user()->forceFill(['countdown_share_token' => Str::random(48)])->save();

        return back()->with('status', 'Neuer Countdown-Link wurde erstellt. Alte Links sind ungültig.');
    }

    public function revokeCountdown(): RedirectResponse
    {
        request()->user()->forceFill(['countdown_share_token' => null])->save();

        return back()->with('status', 'Öffentlicher Countdown-Link wurde deaktiviert.');
    }

    public function storeBirthday(UserBirthdayRequest $request): RedirectResponse
    {
        $request->user()->birthdays()->create($request->validated());

        return back()->with('status', 'Geburtstag wurde hinzugefügt.');
    }

    public function destroyBirthday(UserBirthday $birthday): RedirectResponse
    {
        abort_unless($birthday->user_id === request()->user()->id, 403);

        $birthday->delete();

        return back()->with('status', 'Geburtstag wurde entfernt.');
    }
}
