<?php

namespace App\Actions\Lunch;

use App\Models\Enseigne;

class CreateEnseigne
{
    public function handle(string $name, ?string $urlMenu, ?string $notes, string $createdByUserId): Enseigne
    {
        return Enseigne::create([
            'name' => $name,
            'url_menu' => $urlMenu,
            'notes' => $notes,
            'active' => true,
            'created_by_provider_user_id' => $createdByUserId,
        ]);
    }
}
