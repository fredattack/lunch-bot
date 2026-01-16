<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    protected $fillable = [
        'lunch_day_proposal_id',
        'slack_user_id',
        'description',
        'price_estimated',
        'price_final',
        'notes',
        'audit_log',
    ];

    protected $casts = [
        'price_estimated' => 'decimal:2',
        'price_final' => 'decimal:2',
        'audit_log' => 'array',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(LunchDayProposal::class, 'lunch_day_proposal_id');
    }
}
