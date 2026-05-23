<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'countdown_share_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Trip::class);
    }

    public function sharedTrips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class)->withTimestamps();
    }

    public function emailAliases(): HasMany
    {
        return $this->hasMany(UserEmailAlias::class);
    }

    public function birthdays(): HasMany
    {
        return $this->hasMany(UserBirthday::class);
    }

    public function ensureCountdownShareToken(): string
    {
        if ($this->countdown_share_token) {
            return $this->countdown_share_token;
        }

        $this->forceFill([
            'countdown_share_token' => Str::random(48),
        ])->save();

        return $this->countdown_share_token;
    }
}
