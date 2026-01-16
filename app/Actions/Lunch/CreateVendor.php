<?php

namespace App\Actions\Lunch;

use App\Models\Vendor;

class CreateVendor
{
    public function handle(string $name, ?string $urlMenu, ?string $notes, string $createdByUserId): Vendor
    {
        return Vendor::create([
            'name' => $name,
            'url_menu' => $urlMenu,
            'notes' => $notes,
            'active' => true,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }
}
