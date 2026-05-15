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
    public const CATEGORY_LABELS = [
        'flight' => 'Flug',
        'hotel' => 'Hotel',
        'camper' => 'Camper',
        'car' => 'Auto',
        'ticket' => 'Ticket',
        'transport' => 'Transport',
        'activity' => 'Aktivität',
        'insurance' => 'Versicherung',
        'other' => 'Sonstiges',
    ];
    public const BOOKING_STATUS_LABELS = [
        'open' => 'Offen',
        'requested' => 'Angefragt',
        'confirmed' => 'Bestätigt',
        'cancelled' => 'Storniert',
    ];
    public const PAYMENT_STATUS_LABELS = [
        'unpaid' => 'Unbezahlt',
        'partially_paid' => 'Teilweise bezahlt',
        'paid' => 'Bezahlt',
    ];
    public const CATEGORY_ICONS = [
        'flight' => '✈',
        'hotel' => '▣',
        'camper' => '▤',
        'car' => '◆',
        'ticket' => '▥',
        'transport' => '↔',
        'activity' => '◇',
        'insurance' => '◈',
        'other' => '•',
    ];

    protected $fillable = [
        'trip_id',
        'category',
        'title',
        'provider',
        'booking_reference',
        'amount',
        'currency',
        'start_date',
        'end_date',
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
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? $this->category;
    }

    public function getCategoryIconAttribute(): string
    {
        return self::CATEGORY_ICONS[$this->category] ?? self::CATEGORY_ICONS['other'];
    }

    public function getBookingStatusLabelAttribute(): string
    {
        return self::BOOKING_STATUS_LABELS[$this->booking_status] ?? $this->booking_status;
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        return self::PAYMENT_STATUS_LABELS[$this->payment_status] ?? $this->payment_status;
    }

    public function getExchangeRateToChfAttribute(): float
    {
        return (float) config("exchange.rates_to_chf.{$this->currency}", 1);
    }

    public function getAmountChfAttribute(): float
    {
        return round((float) $this->amount * $this->exchange_rate_to_chf, 2);
    }

    public function getDateRangeLabelAttribute(): string
    {
        if (! $this->start_date && ! $this->end_date) {
            return '-';
        }

        if ($this->start_date && $this->end_date) {
            return $this->start_date->format('d.m.Y').' - '.$this->end_date->format('d.m.Y');
        }

        return $this->start_date?->format('d.m.Y') ?? $this->end_date?->format('d.m.Y');
    }
}
