<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAccountPassword extends Command
{
    protected $signature = 'account:password {email : Exakte Login-Adresse des bestehenden Kontos}';

    protected $description = 'Passwort eines bestehenden Kontos interaktiv setzen und dessen Datenbank-Sessions widerrufen';

    public function handle(): int
    {
        // Never change IDs, ownership, roles or documents to remediate a weak password.
        $user = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($this->argument('email')))])->sole();
        if (config('session.driver') !== 'database') {
            $this->error('Für den Session-Widerruf ist hier ein gesondert geprüfter Ablauf erforderlich; keine Änderung vorgenommen.');

            return self::FAILURE;
        }
        $password = $this->secret('Neues Passwort (mindestens 12 Zeichen)');
        if (mb_strlen((string) $password) < 12 || $password !== $this->secret('Passwort wiederholen')) {
            $this->error('Passwörter stimmen nicht überein oder sind zu kurz.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        });
        $this->info('Passwort gesetzt und Datenbank-Sessions dieses Kontos widerrufen.');

        return self::SUCCESS;
    }
}
