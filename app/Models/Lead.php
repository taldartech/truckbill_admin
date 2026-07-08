<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

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

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @param Builder<Lead> $query */
    public function scopeWithLastActionAt(Builder $query): Builder
    {
        return $query->withMax([
            'activities as last_action_at' => fn ($q) => $q->where('log_name', 'lead'),
        ], 'created_at');
    }

    public function lastActionAt(): Carbon
    {
        return Carbon::parse($this->last_action_at ?? $this->created_at);
    }

    public function lastActionAgo(): string
    {
        return $this->lastActionAt()->diffForHumans();
    }
}
