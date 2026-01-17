<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationInstallation;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::updateOrCreate(
            [
                'provider' => 'slack',
                'provider_team_id' => 'T7P5TRP4H',
            ],
            [
                'name' => 'Hexeko',
                'settings' => [
                    'timezone' => 'Europe/Paris',
                ],
            ]
        );

        OrganizationInstallation::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'bot_token' => config('slack.bot_token'),
                'signing_secret' => config('slack.signing_secret'),
                'installed_by_provider_user_id' => 'U08E9Q2KJGY',
                'default_channel_id' => 'C0A9GLJ67JQ',
                'scopes' => [
                    'chat:write',
                    'chat:write.public',
                    'commands',
                    'users:read',
                    'channels:read',
                    'groups:read',
                ],
                'installed_at' => now(),
            ]
        );
    }
}
