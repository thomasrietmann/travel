<?php

namespace App\Http\Controllers;

use App\Models\UserEmailAlias;
use App\Notifications\VerifyAlias;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Throwable;

class EmailVerificationController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back()->with('status', 'Adresse ist bereits bestätigt.');
        }
        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable) {
            return back()->with('error', 'Bestätigungsmail konnte nicht versendet werden. Bitte später erneut versuchen.');
        }

        return back()->with('status', 'Bestätigungsmail wurde versendet.');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('settings.index')->with('status', 'Adresse wurde bestätigt.');
    }

    public function sendAlias(Request $request, UserEmailAlias $alias): RedirectResponse
    {
        abort_unless($alias->user_id === $request->user()->id, 403);
        if ($alias->verified_at) {
            return back()->with('status', 'Adresse ist bereits bestätigt.');
        }
        try {
            Notification::route('mail', $alias->email)->notify(new VerifyAlias($alias));
        } catch (Throwable) {
            return back()->with('error', 'Bestätigungsmail konnte nicht versendet werden. Bitte später erneut versuchen.');
        }

        return back()->with('status', 'Bestätigungsmail wurde versendet.');
    }

    public function verifyAlias(Request $request, UserEmailAlias $alias, string $hash): RedirectResponse
    {
        abort_unless($alias->user_id === $request->user()->id && hash_equals(sha1($alias->email), $hash), 403);
        $alias->forceFill(['verified_at' => $alias->verified_at ?? now()])->save();

        return redirect()->route('settings.index')->with('status', 'Adresse wurde bestätigt.');
    }
}
