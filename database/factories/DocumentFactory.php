<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'booking_id' => null,
            'title' => fake()->words(3, true),
            'file_path' => 'documents/example.pdf',
            'document_type' => fake()->randomElement(Document::TYPES),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
