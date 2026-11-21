<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_flight_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_path');
            $table->char('content_hash', 64);
            $table->string('status', 20);
            $table->json('segments')->nullable();
            $table->timestamps();
            $table->unique(['booking_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_flight_extractions');
    }
};
