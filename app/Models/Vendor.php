<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Vendor extends Model implements HasMedia
{
    use BelongsToOrganization;
    use HasFactory;
    use InteractsWithMedia;

    protected $attributes = [
        'fulfillment_types' => '["pickup"]',
        'allow_individual_order' => false,
    ];

    protected $fillable = [
        'organization_id',
        'name',
        'cuisine_type',
        'fulfillment_types',
        'allow_individual_order',
        'url_website',
        'url_menu',
        'notes',
        'active',
        'created_by_provider_user_id',
    ];

    protected $casts = [
        'fulfillment_types' => 'array',
        'allow_individual_order' => 'boolean',
        'active' => 'boolean',
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(VendorProposal::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('menu')->singleFile();
    }
}
