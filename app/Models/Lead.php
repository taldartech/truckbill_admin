<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

class Lead extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'name',
        'company_name',
        'mobile',
        'email',
        'city',
        'state',
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
        'next_follow_up_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'product' => Product::class,
            'converted_at' => 'datetime',
            'next_follow_up_at' => 'date',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
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

    public function toArray(): array
    {
        $array = parent::toArray();

        if (array_key_exists('next_follow_up_at', $array)) {
            $array['next_follow_up_at'] = $this->next_follow_up_at?->toDateString();
        }

        return $array;
    }

    public function followUpStatus(): ?string
    {
        if ($this->next_follow_up_at === null) {
            return null;
        }

        $today = now()->startOfDay();
        $followUp = $this->next_follow_up_at->startOfDay();

        if ($followUp->lt($today)) {
            return 'overdue';
        }

        if ($followUp->equalTo($today)) {
            return 'due_today';
        }

        return 'upcoming';
    }

    /** @param Builder<Lead> $query */
    public function scopeFollowUpFilter(Builder $query, string $filter): Builder
    {
        $today = now()->toDateString();

        return match ($filter) {
            'overdue' => $query
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', '<', $today),
            'due_today' => $query
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', $today),
            'upcoming' => $query
                ->whereNotNull('next_follow_up_at')
                ->whereDate('next_follow_up_at', '>', $today),
            default => $query,
        };
    }
}
