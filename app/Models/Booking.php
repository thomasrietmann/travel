<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    /** @use HasFactory<\Database\Factories\BookingFactory> */
    use HasFactory;

    public const CATEGORIES = ['flight', 'hotel', 'camper', 'car', 'ticket', 'transport', 'activity', 'insurance', 'other'];
    public const CURRENCIES = ['CHF', 'EUR', 'USD', 'SEK', 'NOK'];
    public const BOOKING_STATUSES = ['open', 'requested', 'confirmed', 'cancelled'];
    public const PAYMENT_STATUSES = ['unpaid', 'partially_paid', 'paid'];

    protected $fillable = [
        'trip_id',
        'category',
        'title',
        'provider',
        'booking_reference',
        'amount',
        'currency',
        'booking_status',
        'payment_status',
        'due_date',
        'cancellation_deadline',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'cancellation_deadline' => 'date',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
