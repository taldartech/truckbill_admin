<?php

namespace App\Models;

use App\Models\Concerns\SoftDeletesOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProvisioningLog extends Model
{
    use SoftDeletesOnly;

    protected $fillable = [
        'onboarding_invitation_id',
        'idempotency_key',
        'product',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(OnboardingInvitation::class, 'onboarding_invitation_id');
    }
}
