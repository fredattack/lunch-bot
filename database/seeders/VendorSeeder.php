<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('provider_team_id', 'T7P5TRP4H')->first();

        if (! $organization) {
            $this->command->warn('Organization T7P5TRP4H not found. Run OrganizationSeeder first.');

            return;
        }

        Vendor::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Tatie Crouton',
            ],
            [
                'cuisine_type' => 'Sandwichs',
                'url_website' => 'https://www.tatiecroutons.com/fr/',
                'active' => true,
                'created_by_provider_user_id' => 'U08E9Q2KJGY',
            ]
        );

        Vendor::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Quick',
            ],
            [
                'cuisine_type' => 'Fast food',
                'active' => true,
                'created_by_provider_user_id' => 'U08E9Q2KJGY',
            ]
        );
    }
}
