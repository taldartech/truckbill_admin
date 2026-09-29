<?php

namespace App\Models;

use App\Enums\Product;
use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OnboardingInvitation extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'lead_id',
        'product',
        'company_name',
        'email',
        'mobile',
        'token_hash',
        'expires_at',
        'used_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'product' => Product::class,
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function session(): HasOne
    {
        return $this->hasOne(OnboardingSession::class);
    }

    public function provisioningLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProvisioningLog::class);
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
