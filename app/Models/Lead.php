<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'mobile',
        'email',
        'product',
        'source',
        'remarks',
        'status',
        'assigned_to',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_url',
        'referrer',
        'ip_address',
        'rejection_reason',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'product' => Product::class,
            'converted_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(OnboardingInvitation::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }
}
