<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\OrderingMode;
use App\Enums\ProposalStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProposal extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'lunch_session_id',
        'vendor_id',
        'fulfillment_type',
        'ordering_mode',
        'runner_user_id',
        'orderer_user_id',
        'platform',
        'status',
        'provider_message_ts',
        'created_by_provider_user_id',
    ];

    protected $casts = [
        'fulfillment_type' => FulfillmentType::class,
        'ordering_mode' => OrderingMode::class,
        'status' => ProposalStatus::class,
    ];

    public function lunchSession(): BelongsTo
    {
        return $this->belongsTo(LunchSession::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasRole(string $userId): bool
    {
        return $this->runner_user_id === $userId || $this->orderer_user_id === $userId;
    }

    public function getRoleFor(string $userId): ?string
    {
        if ($this->runner_user_id === $userId) {
            return 'runner';
        }

        if ($this->orderer_user_id === $userId) {
            return 'orderer';
        }

        return null;
    }

    public function isRunner(string $userId): bool
    {
        return $this->runner_user_id === $userId;
    }

    public function isOrderer(string $userId): bool
    {
        return $this->orderer_user_id === $userId;
    }
}
