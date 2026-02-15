<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickRunRequest extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'quick_run_id',
        'provider_user_id',
        'description',
        'price_estimated',
        'price_final',
        'notes',
    ];

    protected $casts = [
        'price_estimated' => 'decimal:2',
        'price_final' => 'decimal:2',
    ];

    public function quickRun(): BelongsTo
    {
        return $this->belongsTo(QuickRun::class);
    }
}
