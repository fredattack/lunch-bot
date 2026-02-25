<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('provider_team_id', config('slack.team_id'))->first();

        if (! $organization) {
            $this->command->warn('Organization not found. Run OrganizationSeeder first.');

            return;
        }

        Vendor::query()->delete();

        Vendor::insert([
            [
                'organization_id' => $organization->id,
                'name' => 'Tatie Crouton',
                'cuisine_type' => 'Sandwichs',
                'fulfillment_types' => json_encode(['pickup']),
                'allow_individual_order' => false,
                'url_website' => 'https://www.tatiecroutons.com/fr/',
                'url_menu' => null,
                'notes' => null,
                'active' => true,
                'created_by_provider_user_id' => config('slack.admin_user_ids.0', 'UNKNOWN'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organization->id,
                'name' => 'Quick',
                'cuisine_type' => 'Fast food',
                'fulfillment_types' => json_encode(['pickup', 'delivery']),
                'allow_individual_order' => false,
                'url_website' => null,
                'url_menu' => null,
                'notes' => null,
                'active' => true,
                'created_by_provider_user_id' => config('slack.admin_user_ids.0', 'UNKNOWN'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'organization_id' => $organization->id,
                'name' => 'Laurent Dumont',
                'cuisine_type' => null,
                'fulfillment_types' => json_encode(['pickup']),
                'allow_individual_order' => false,
                'url_website' => 'https://webshop.laurentdumont.be/be-fr/laurentdumontgenval/overview',
                'url_menu' => null,
                'notes' => null,
                'active' => true,
                'created_by_provider_user_id' => config('slack.admin_user_ids.0', 'UNKNOWN'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
