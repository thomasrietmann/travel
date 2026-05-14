<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    public const TYPES = ['ticket', 'confirmation', 'invoice', 'passport', 'insurance', 'other'];
    public const TYPE_LABELS = [
        'ticket' => 'Ticket',
        'confirmation' => 'Bestätigung',
        'invoice' => 'Rechnung',
        'passport' => 'Pass',
        'insurance' => 'Versicherung',
        'other' => 'Sonstiges',
    ];

    protected $fillable = [
        'trip_id',
        'booking_id',
        'title',
        'file_path',
        'document_type',
        'notes',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function getDocumentTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->document_type] ?? $this->document_type;
    }
}
