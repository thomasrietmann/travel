<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserBirthdayRequest;
use App\Http\Requests\UserEmailAliasRequest;
use App\Models\UserBirthday;
use App\Models\UserEmailAlias;
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
            'shareUrl' => route('countdown.public', $user->ensureCountdownShareToken()),
        ]);
    }

    public function storeEmailAlias(UserEmailAliasRequest $request): RedirectResponse
    {
        $request->user()->emailAliases()->create([
            'email' => Str::lower($request->validated('email')),
        ]);

        return back()->with('status', 'E-Mail-Adresse wurde hinzugefügt.');
    }

    public function destroyEmailAlias(UserEmailAlias $alias): RedirectResponse
    {
        abort_unless($alias->user_id === request()->user()->id, 403);

        $alias->delete();

        return back()->with('status', 'E-Mail-Adresse wurde entfernt.');
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
