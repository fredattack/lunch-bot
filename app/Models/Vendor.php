<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Vendor extends Model implements HasMedia
{
    use BelongsToOrganization;
    use HasFactory;
    use InteractsWithMedia;

    protected $attributes = [
        'fulfillment_types' => '["pickup"]',
        'allow_individual_order' => false,
    ];

    private const CUISINE_EMOJI_MAP = [
        'burger' => "\u{1F354}",
        'pizza' => "\u{1F355}",
        'sushi' => "\u{1F363}",
        'japanese' => "\u{1F363}",
        'asian' => "\u{1F35C}",
        'chinese' => "\u{1F961}",
        'thai' => "\u{1F35B}",
        'indian' => "\u{1F35B}",
        'mexican' => "\u{1F32E}",
        'italian' => "\u{1F35D}",
        'french' => "\u{1F950}",
        'kebab' => "\u{1F959}",
        'sandwich' => "\u{1F96A}",
        'salad' => "\u{1F957}",
        'seafood' => "\u{1F990}",
        'chicken' => "\u{1F357}",
        'bbq' => "\u{1F356}",
        'vegan' => "\u{1F966}",
        'vegetarian' => "\u{1F966}",
        'bakery' => "\u{1F950}",
        'dessert' => "\u{1F370}",
        'coffee' => "\u{2615}",
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
        'emoji_name',
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 128, 128)
            ->nonQueued()
            ->performOnCollections('logo');
    }

    public function getLogoThumbUrl(): ?string
    {
        $thumbUrl = $this->getFirstMediaUrl('logo', 'thumb');
        if ($thumbUrl) {
            return $thumbUrl;
        }

        return $this->getFirstMediaUrl('logo') ?: null;
    }

    public function getEmojiMarkdown(): string
    {
        if ($this->emoji_name) {
            return ":{$this->emoji_name}: ";
        }

        if ($this->cuisine_type) {
            $key = strtolower(trim($this->cuisine_type));

            foreach (self::CUISINE_EMOJI_MAP as $keyword => $emoji) {
                if (str_contains($key, $keyword)) {
                    return "{$emoji} ";
                }
            }
        }

        return "\u{1F37D}\u{FE0F} ";
    }
}
