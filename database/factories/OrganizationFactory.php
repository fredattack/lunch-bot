<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider' => 'slack',
            'provider_team_id' => 'T'.fake()->regexify('[A-Z0-9]{10}'),
            'name' => fake()->company(),
            'settings' => [
                'timezone' => 'Europe/Paris',
            ],
        ];
    }

    public function withInstallation(): static
    {
        return $this->has(
            \App\Models\OrganizationInstallation::factory(),
            'installation'
        );
    }
}
