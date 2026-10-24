<?php

namespace App\Notifications;

use App\Models\UserEmailAlias;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyAlias extends Notification
{
    public function __construct(private readonly UserEmailAlias $alias) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('TripControl: Mailadresse bestätigen')
            ->line('Bestätige diese Adresse für den Mailimport in dein TripControl-Konto. Öffne den Link, während du in diesem Konto angemeldet bist.')
            ->action('Adresse bestätigen', URL::temporarySignedRoute('settings.email-aliases.verify', now()->addHour(), [
                'alias' => $this->alias->id, 'hash' => sha1($this->alias->email),
            ]))
            ->line('Falls du diese Anfrage nicht gestellt hast, ignoriere diese Nachricht.');
    }
}
