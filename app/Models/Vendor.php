<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'cuisine_type',
        'url_website',
        'url_menu',
        'notes',
        'active',
        'created_by_provider_user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(VendorProposal::class);
    }
}
