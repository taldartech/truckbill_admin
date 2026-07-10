<?php

namespace App\Services;

use App\Models\PartnerOnboardingInvitation;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Notifications\SalesPartnerWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class PartnerOnboardingService
{
    public function createInvitation(User $user, ?int $createdBy = null): array
    {
        PartnerOnboardingInvitation::query()
            ->where('user_id', $user->id)
            ->whereNull('completed_at')
            ->update(['completed_at' => now()]);

        $plainToken = Str::random(64);
        $ttlDays = (int) config('partner_onboarding.invitation_ttl_days', 7);

        $invitation = PartnerOnboardingInvitation::create([
            'user_id' => $user->id,
            'token_hash' => $this->hashToken($plainToken),
            'expires_at' => now()->addDays($ttlDays),
            'created_by' => $createdBy,
        ]);

        return [
            'plain_token' => $plainToken,
            'invitation' => $invitation,
            'url' => route('partner-onboarding.show', ['token' => $plainToken]),
        ];
    }

    public function findActiveByPlainToken(string $plainToken): ?PartnerOnboardingInvitation
    {
        return PartnerOnboardingInvitation::query()
            ->with('user')
            ->where('token_hash', $this->hashToken($plainToken))
            ->whereNull('completed_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function complete(PartnerOnboardingInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data): User {
            $user = $invitation->user;

            SalesPartnerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'legal_name' => $data['legal_name'],
                    'mobile' => $data['mobile'],
                    'working_location' => $data['working_location'] ?? null,
                    'current_company_name' => $data['current_company_name'] ?? null,
                    'pan_number' => $data['pan_number'] ?? null,
                    'aadhar_number' => $data['aadhar_number'] ?? null,
                    'bank_name' => $data['bank_name'] ?? null,
                    'bank_account_number' => $data['bank_account_number'] ?? null,
                    'ifsc_code' => $data['ifsc_code'] ?? null,
                    'onboarding_completed_at' => now(),
                ],
            );

            $user->update([
                'name' => $data['legal_name'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $invitation->update(['completed_at' => now()]);

            Notification::send($user, new SalesPartnerWelcomeNotification($user->fresh()));

            activity('user')
                ->performedOn($user)
                ->causedBy($user)
                ->log('Sales partner onboarding completed');

            return $user->fresh(['salesPartnerProfile']);
        });
    }

    public function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, (string) config('app.key'));
    }
}
