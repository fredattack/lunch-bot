<?php

namespace App\Models;

use App\Enums\FulfillmentType;
use App\Enums\ProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LunchDayProposal extends Model
{
    protected $fillable = [
        'lunch_day_id',
        'enseigne_id',
        'fulfillment_type',
        'runner_user_id',
        'orderer_user_id',
        'platform',
        'status',
        'slack_message_ts',
        'created_by_slack_user_id',
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
}
