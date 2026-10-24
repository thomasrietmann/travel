<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 month', '+1 year');

        return [
            'user_id' => User::factory(),
            'title' => fake()->city().' Trip',
            'type' => fake()->randomElement(Trip::TYPES),
            'destination' => fake()->country(),
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->modify('+14 days'),
            'status' => fake()->randomElement(Trip::STATUSES),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
