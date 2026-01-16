<?php

namespace App\Models;

use App\Enums\LunchSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LunchSession extends Model
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
        'status' => LunchSessionStatus::class,
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(VendorProposal::class);
    }

    public function isOpen(): bool
    {
        return $this->status === LunchSessionStatus::Open;
    }

    public function isLocked(): bool
    {
        return $this->status === LunchSessionStatus::Locked;
    }

    public function isClosed(): bool
    {
        return $this->status === LunchSessionStatus::Closed;
    }
}
