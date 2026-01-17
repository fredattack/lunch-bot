<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use BelongsToOrganization;
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'vendor_proposal_id',
        'provider_user_id',
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
        return $this->belongsTo(VendorProposal::class, 'vendor_proposal_id');
    }

    public function vendorProposal(): BelongsTo
    {
        return $this->proposal();
    }
}
