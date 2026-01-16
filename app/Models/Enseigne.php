<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enseigne extends Model
{
    protected $fillable = [
        'name',
        'url_menu',
        'notes',
        'active',
        'created_by_provider_user_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function proposals(): HasMany
    {
        return $this->hasMany(LunchDayProposal::class);
    }
}
