<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use Illuminate\Support\Str;

class OnboardingInvitationService
{
    public function createForLead(Lead $lead, string $product, ?int $createdBy = null): array
    {
        OnboardingInvitation::query()
            ->where('lead_id', $lead->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $plainToken = Str::random(64);
        $ttlDays = (int) config('onboarding.invitation_ttl_days', 7);

        $invitation = OnboardingInvitation::create([
            'lead_id' => $lead->id,
            'product' => $product,
            'company_name' => $lead->company_name,
            'email' => $lead->email,
            'mobile' => $lead->mobile,
            'token_hash' => $this->hashToken($plainToken),
            'expires_at' => now()->addDays($ttlDays),
            'created_by' => $createdBy,
        ]);

        OnboardingSession::create([
            'onboarding_invitation_id' => $invitation->id,
        ]);

        return [
            'plain_token' => $plainToken,
            'invitation' => $invitation,
            'url' => rtrim((string) config('crm.onboarding_frontend_url'), '/').'/onboarding/'.$plainToken,
        ];
    }

    public function findActiveByPlainToken(string $plainToken): ?OnboardingInvitation
    {
        return OnboardingInvitation::query()
            ->with(['lead', 'session'])
            ->where('token_hash', $this->hashToken($plainToken))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markUsed(OnboardingInvitation $invitation): void
    {
        $invitation->update(['used_at' => now()]);
    }

    public function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }
}
