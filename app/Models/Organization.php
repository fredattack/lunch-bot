<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Context;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'provider_team_id',
        'name',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public static function current(): ?self
    {
        return Context::get('organization');
    }

    public static function setCurrent(?self $organization): void
    {
        if ($organization) {
            Context::add('organization', $organization);
        } else {
            Context::forget('organization');
        }
    }

    public function installation(): HasOne
    {
        return $this->hasOne(OrganizationInstallation::class);
    }

    public function lunchSessions(): HasMany
    {
        return $this->hasMany(LunchSession::class);
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }
}
