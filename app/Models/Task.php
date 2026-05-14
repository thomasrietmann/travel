<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    public const PRIORITIES = ['low', 'medium', 'high'];
    public const STATUSES = ['open', 'done'];
    public const PRIORITY_LABELS = [
        'low' => 'Niedrig',
        'medium' => 'Mittel',
        'high' => 'Hoch',
    ];
    public const STATUS_LABELS = [
        'open' => 'Offen',
        'done' => 'Erledigt',
    ];

    protected $fillable = [
        'trip_id',
        'title',
        'due_date',
        'priority',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITY_LABELS[$this->priority] ?? $this->priority;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
