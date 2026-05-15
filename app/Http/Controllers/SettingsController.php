<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserEmailAliasRequest;
use App\Models\UserEmailAlias;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'user' => request()->user()->load('emailAliases'),
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
}
