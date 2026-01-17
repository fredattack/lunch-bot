<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'bot_token',
        'signing_secret',
        'installed_by_provider_user_id',
        'default_channel_id',
        'scopes',
        'installed_at',
    ];

    protected function casts(): array
    {
        return [
            'bot_token' => 'encrypted',
            'signing_secret' => 'encrypted',
            'scopes' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
