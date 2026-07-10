<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $manager_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'manager_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function salesPartnerProfile(): HasOne
    {
        return $this->hasOne(SalesPartnerProfile::class);
    }

    public function partnerOnboardingInvitations(): HasMany
    {
        return $this->hasMany(PartnerOnboardingInvitation::class);
    }

    public function isSalesPartner(): bool
    {
        return $this->hasRole('Sales Partner');
    }

    public function hasCompletedPartnerOnboarding(): bool
    {
        return $this->salesPartnerProfile?->onboarding_completed_at !== null;
    }

    public function partnerOnboardingStatus(): ?string
    {
        if (! $this->isSalesPartner()) {
            return null;
        }

        if ($this->salesPartnerProfile?->onboarding_completed_at !== null) {
            return 'onboarded';
        }

        $invitations = $this->relationLoaded('partnerOnboardingInvitations')
            ? $this->partnerOnboardingInvitations
            : $this->partnerOnboardingInvitations()->get();

        $hasPendingInvitation = $invitations
            ->whereNull('completed_at')
            ->contains(fn (PartnerOnboardingInvitation $invitation) => $invitation->expires_at->isFuture());

        return $hasPendingInvitation ? 'pending' : 'skipped';
    }

    /** @return list<string> */
    public static function assignedLeadRoleNames(): array
    {
        return ['Sales Executive', 'Sales Partner'];
    }

    public function hasAssignedLeadScope(): bool
    {
        return $this->hasAnyRole(self::assignedLeadRoleNames());
    }
}
