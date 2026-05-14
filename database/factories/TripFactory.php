<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
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
            'end_date' => fake()->dateTimeBetween($startDate, '+14 days'),
            'status' => fake()->randomElement(Trip::STATUSES),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
