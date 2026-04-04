<?php

namespace Database\Factories;

use App\Enum\TransferEventStatus;
use App\Models\TransferEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferEvent>
 */
class TransferEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => fake()->uuid(),
            'station_id' => fake()->randomElement(['S1', 'S2', 'S3']),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'status' => fake()->randomElement(TransferEventStatus::values()),
            'batch_id' => fake()->randomNumber(10),
            'created_at' => fake()->dateTime(),
        ];
    }
}
