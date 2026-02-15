<?php

namespace App\Models;

use App\Enums\QuickRunStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuickRun extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'provider_user_id',
        'destination',
        'vendor_id',
        'deadline_at',
        'status',
        'note',
        'provider_channel_id',
        'provider_message_ts',
    ];

    protected $casts = [
        'deadline_at' => 'datetime',
        'status' => QuickRunStatus::class,
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(QuickRunRequest::class);
    }

    public function isOpen(): bool
    {
        return $this->status === QuickRunStatus::Open;
    }

    public function isLocked(): bool
    {
        return $this->status === QuickRunStatus::Locked;
    }

    public function isClosed(): bool
    {
        return $this->status === QuickRunStatus::Closed;
    }

    public function isRunner(string $userId): bool
    {
        return $this->provider_user_id === $userId;
    }
}
