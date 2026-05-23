<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserBirthday extends Model
{
    protected $fillable = [
        'name',
        'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nextBirthdayDate(): Carbon
    {
        $today = today();
        $nextBirthday = $this->birthdayDateForYear($today->year);

        if ($nextBirthday->isBefore($today)) {
            $nextBirthday = $this->birthdayDateForYear($today->year + 1);
        }

        return $nextBirthday;
    }

    public function getNextBirthdayLabelAttribute(): string
    {
        $days = (int) today()->diffInDays($this->nextBirthdayDate(), false);

        return match (true) {
            $days === 0 => 'Heute',
            $days === 1 => 'Morgen',
            default => "Noch {$days} Tage",
        };
    }

    public function getAgeOnNextBirthdayAttribute(): int
    {
        return $this->nextBirthdayDate()->year - $this->birth_date->year;
    }

    private function birthdayDateForYear(int $year): Carbon
    {
        $month = (int) $this->birth_date->format('m');
        $day = min((int) $this->birth_date->format('d'), Carbon::create($year, $month, 1)->daysInMonth);

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
