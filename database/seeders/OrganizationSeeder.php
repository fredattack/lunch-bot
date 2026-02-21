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
                'provider_team_id' => config('slack.team_id', 'T0AFH2Q89TK'),
            ],
            [
                'name' => config('app.name', 'Lunch Bot'),
                'settings' => [
                    'timezone' => config('lunch.timezone', 'Europe/Paris'),
                ],
            ]
        );

        OrganizationInstallation::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'bot_token' => config('slack.bot_token'),
                'signing_secret' => config('slack.signing_secret'),
                'installed_by_provider_user_id' => config('slack.admin_user_ids.0'),
                'default_channel_id' => config('lunch.channel_id'),
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
