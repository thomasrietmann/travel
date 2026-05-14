<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'title' => fake()->sentence(4),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+6 months'),
            'priority' => fake()->randomElement(Task::PRIORITIES),
            'status' => fake()->randomElement(Task::STATUSES),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
