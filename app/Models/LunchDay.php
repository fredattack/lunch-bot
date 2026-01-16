<?php

namespace App\Models;

use App\Enums\LunchDayStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LunchDay extends Model
{
    protected $fillable = [
        'date',
        'provider',
        'provider_channel_id',
        'provider_message_ts',
        'deadline_at',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'deadline_at' => 'datetime',
        'status' => LunchDayStatus::class,
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(LunchDayProposal::class);
    }
}
