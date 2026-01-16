<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LunchDayProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'lunch_day_id',
        'enseigne_id',
        'fulfillment_type',
        'runner_user_id',
        'orderer_user_id',
        'platform',
        'status',
        'provider_message_ts',
        'created_by_provider_user_id',
    ];

    protected $casts = [
        'fulfillment_type' => FulfillmentType::class,
        'status' => ProposalStatus::class,
    ];

    public function lunchDay(): BelongsTo
    {
        return $this->belongsTo(LunchDay::class);
    }

    public function enseigne(): BelongsTo
    {
        return $this->belongsTo(Enseigne::class);
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
