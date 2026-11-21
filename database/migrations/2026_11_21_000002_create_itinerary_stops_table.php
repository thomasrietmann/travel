<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itinerary_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->time('start_time')->nullable();
            $table->string('start_location')->nullable();
            $table->string('start_timezone', 64)->nullable();
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->string('end_location')->nullable();
            $table->string('end_timezone', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('itinerary_stops');
    }
};
