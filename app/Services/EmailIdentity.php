<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserEmailAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailIdentity
{
    /** Serialize writes across both tables, including concurrent requests on MySQL. */
    public function create(string $email, callable $create): mixed
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($email, $create) {
            DB::table('email_identity_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
                || UserEmailAlias::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email' => 'Diese E-Mail-Adresse ist bereits hinterlegt.']);
            }

            return $create($email);
        });
    }

    /** Ambiguous legacy identities are never assigned to an arbitrary owner. */
    public function verifiedUser(string $email): ?User
    {
        $email = mb_strtolower(trim($email));
        $users = User::query()->whereRaw('LOWER(email) = ?', [$email])->get();
        $aliases = UserEmailAlias::query()->with('user')->whereRaw('LOWER(email) = ?', [$email])->get();
        $owners = $users->pluck('id')->merge($aliases->pluck('user_id'))->unique();
        if ($owners->count() !== 1) {
            return null;
        }
        $user = $users->first(fn (User $user) => $user->email_verified_at !== null)
            ?? $aliases->first(fn (UserEmailAlias $alias) => $alias->verified_at !== null)?->user;

        return $user && ! $user->is_admin ? $user : null;
    }
}
