<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationInstallation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationInstallation>
 */
class OrganizationInstallationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'bot_token' => 'xoxb-'.fake()->regexify('[0-9]{12}-[0-9]{13}-[a-zA-Z0-9]{24}'),
            'signing_secret' => fake()->regexify('[a-f0-9]{32}'),
            'installed_by_provider_user_id' => 'U'.fake()->regexify('[A-Z0-9]{10}'),
            'default_channel_id' => 'C'.fake()->regexify('[A-Z0-9]{10}'),
            'scopes' => [
                'chat:write',
                'chat:write.public',
                'commands',
                'users:read',
            ],
            'installed_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
