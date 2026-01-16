<?php

namespace Database\Factories;

use App\Enums\LunchDayStatus;
use App\Models\LunchDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LunchDay>
 */
class LunchDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('now', '+1 week'),
            'provider' => 'slack',
            'provider_channel_id' => 'C'.fake()->regexify('[A-Z0-9]{10}'),
            'provider_message_ts' => (string) fake()->unixTime().'.000000',
            'deadline_at' => fake()->dateTimeBetween('now', '+1 week'),
            'status' => LunchDayStatus::Open,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => LunchDayStatus::Open]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => LunchDayStatus::Locked]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => LunchDayStatus::Closed]);
    }
}
