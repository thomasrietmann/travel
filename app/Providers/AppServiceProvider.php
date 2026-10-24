<?php

namespace App\Providers;

use App\Services\ImapMailbox;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One shared mailbox connection for importer and MIME reader.
        $this->app->singleton(ImapMailbox::class);
    }

    public function boot(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event): void {
            if (! app()->environment('staging')) {
                return;
            }
            $recipient = (string) config('staging.mail_recipient');
            $addresses = [...$event->message->getTo(), ...$event->message->getCc(), ...$event->message->getBcc()];
            if (! filter_var($recipient, FILTER_VALIDATE_EMAIL) || $addresses === []
                || collect($addresses)->contains(fn ($address) => strcasecmp($address->getAddress(), $recipient) !== 0)) {
                throw new \RuntimeException('Stagingversand ist nur an STAGING_MAIL_RECIPIENT erlaubt.');
            }
        });
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinutes(15, 5)->by('identity:'.hash_hmac('sha256', mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip(), config('app.key'))),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinutes(15, 6)->by($request->user()->id));
        RateLimiter::for('ai-import', fn (Request $request) => Limit::perMinute(2)->by($request->user()->id));
        RateLimiter::for('writes', fn (Request $request) => $request->isMethod('GET') ? Limit::none() : Limit::perMinute(60)->by($request->user()->id));
    }
}
