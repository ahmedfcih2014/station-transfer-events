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
            'station_id' => fake()->randomElement(['Station-1', 'Station-2', 'Station-3']),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'status' => TransferEventStatus::APPROVED->value,
            'batch_id' => fake()->numberBetween(1000000000, 9999999999),
            'created_at' => fake()->dateTime(),
        ];
    }
}
