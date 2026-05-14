<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    /** @use HasFactory<\Database\Factories\TripFactory> */
    use HasFactory;

    public const TYPES = ['family_camper', 'coastertrip', 'roadtrip', 'citytrip', 'other'];
    public const STATUSES = ['idea', 'planned', 'booked', 'ready', 'completed'];

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'destination',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->bookings->sum('amount');
    }

    public function getPaidAmountAttribute(): float
    {
        return (float) $this->bookings
            ->where('payment_status', 'paid')
            ->sum('amount');
    }

    public function getOpenAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function getBookingCompletionPercentageAttribute(): int
    {
        $total = $this->bookings->count();

        if ($total === 0) {
            return 100;
        }

        $confirmed = $this->bookings
            ->where('booking_status', 'confirmed')
            ->count();

        return (int) round(($confirmed / $total) * 100);
    }

    public function getPaymentCompletionPercentageAttribute(): int
    {
        $total = $this->bookings->count();

        if ($total === 0) {
            return 100;
        }

        $paid = $this->bookings
            ->where('payment_status', 'paid')
            ->count();

        return (int) round(($paid / $total) * 100);
    }

    public function getOpenTasksCountAttribute(): int
    {
        return $this->tasks->where('status', 'open')->count();
    }

    public function getNextDueDateAttribute(): ?Carbon
    {
        return collect([
            ...$this->bookings->pluck('due_date')->filter(),
            ...$this->tasks->where('status', 'open')->pluck('due_date')->filter(),
            ...$this->bookings->pluck('cancellation_deadline')->filter(),
        ])->sort()->first();
    }

    public function getHasOverdueItemsAttribute(): bool
    {
        return $this->tasks->where('status', 'open')->contains(fn (Task $task) => $task->due_date?->isPast())
            || $this->bookings->whereIn('payment_status', ['unpaid', 'partially_paid'])
                ->contains(fn (Booking $booking) => $booking->due_date?->isPast());
    }

    public function getTrafficLightAttribute(): string
    {
        if ($this->has_overdue_items) {
            return 'red';
        }

        if ($this->open_amount > 0 || $this->open_tasks_count > 0 || $this->booking_completion_percentage < 100) {
            return 'yellow';
        }

        return 'green';
    }
}
