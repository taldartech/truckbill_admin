<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingSession extends Model
{
    protected $hidden = [
        'password_hash',
    ];

    protected $fillable = [
        'onboarding_invitation_id',
        'email_verified_at',
        'policy_accepted_at',
        'policy_version',
        'password_set_at',
        'password_hash',
        'completed_at',
        'otp_attempts',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'password_set_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(OnboardingInvitation::class, 'onboarding_invitation_id');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }
}
