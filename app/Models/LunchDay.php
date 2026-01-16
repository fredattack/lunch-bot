<?php

namespace App\Models;

use App\Enums\LunchDayStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LunchDay extends Model
{
    use HasFactory;

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

    public function isOpen(): bool
    {
        return $this->status === LunchDayStatus::Open;
    }

    public function isLocked(): bool
    {
        return $this->status === LunchDayStatus::Locked;
    }

    public function isClosed(): bool
    {
        return $this->status === LunchDayStatus::Closed;
    }
}
