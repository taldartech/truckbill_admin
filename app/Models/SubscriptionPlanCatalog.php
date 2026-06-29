<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanCatalog extends Model
{
    protected $table = 'subscription_plan_catalog';

    protected $fillable = [
        'product',
        'base_price',
        'included_branches',
        'users_per_branch',
        'included_lr_credits',
        'included_trucks',
        'addon_branch_price',
        'addon_user_price',
        'addon_truck_price',
        'is_active',
        'effective_from',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'addon_branch_price' => 'decimal:2',
            'addon_user_price' => 'decimal:2',
            'addon_truck_price' => 'decimal:2',
            'is_active' => 'boolean',
            'effective_from' => 'date',
        ];
    }

    public function toCatalogArray(): array
    {
        return [
            'product' => $this->product,
            'base_price' => (float) $this->base_price,
            'included_branches' => $this->included_branches,
            'users_per_branch' => $this->users_per_branch,
            'included_lr_credits' => $this->included_lr_credits,
            'included_trucks' => $this->included_trucks,
            'addon_branch_price' => (float) $this->addon_branch_price,
            'addon_user_price' => (float) $this->addon_user_price,
            'addon_truck_price' => (float) $this->addon_truck_price,
            'effective_from' => $this->effective_from?->toDateString(),
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
