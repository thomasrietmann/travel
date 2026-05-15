<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'category' => fake()->randomElement(Booking::CATEGORIES),
            'title' => fake()->words(3, true),
            'provider' => fake()->optional()->company(),
            'booking_reference' => fake()->optional()->bothify('??-#####'),
            'amount' => fake()->randomFloat(2, 0, 2500),
            'currency' => fake()->randomElement(Booking::CURRENCIES),
            'start_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'end_date' => null,
            'booking_status' => fake()->randomElement(Booking::BOOKING_STATUSES),
            'payment_status' => fake()->randomElement(Booking::PAYMENT_STATUSES),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'cancellation_deadline' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
