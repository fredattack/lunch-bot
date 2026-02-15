<?php

namespace Database\Factories;

use App\Enums\QuickRunStatus;
use App\Models\Organization;
use App\Models\QuickRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickRun>
 */
class QuickRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory()->withInstallation(),
            'provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'destination' => fake()->company(),
            'vendor_id' => null,
            'deadline_at' => now()->addMinutes(15),
            'status' => QuickRunStatus::Open,
            'note' => null,
            'provider_channel_id' => 'C'.fake()->regexify('[A-Z0-9]{10}'),
            'provider_message_ts' => (string) fake()->unixTime().'.000000',
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => QuickRunStatus::Open]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['status' => QuickRunStatus::Locked]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => QuickRunStatus::Closed]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => QuickRunStatus::Open,
            'deadline_at' => now()->subMinutes(5),
        ]);
    }
}
