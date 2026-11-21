<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingFlightExtraction extends Model
{
    protected $fillable = ['document_id', 'document_path', 'content_hash', 'status', 'segments'];

    protected function casts(): array
    {
        return ['segments' => 'array'];
    }
}
