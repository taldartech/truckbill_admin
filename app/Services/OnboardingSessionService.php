<?php

namespace App\Services;

use App\Enums\Product;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use App\Notifications\OnboardingWelcomeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OnboardingSessionService
{
    public function __construct(
        private readonly OnboardingOtpService $otpService,
        private readonly OnboardingInvitationService $invitationService,
        private readonly ProvisioningOrchestrator $provisioningOrchestrator,
        private readonly LeadService $leadService,
    ) {}

    public function resolveInvitation(string $token): OnboardingInvitation
    {
        $invitation = $this->invitationService->findActiveByPlainToken($token);

        if ($invitation === null) {
            throw ValidationException::withMessages(['token' => 'Invalid or expired onboarding link.']);
        }

        return $invitation;
    }

    public function sendOtp(OnboardingInvitation $invitation): void
    {
        $this->otpService->send($invitation->email);
    }

    public function verifyOtp(OnboardingInvitation $invitation, string $otp): void
    {
        $session = $this->sessionFor($invitation);
        $this->otpService->verify($invitation->email, $otp);
        $session->update(['email_verified_at' => now()]);
    }

    public function acceptPolicies(OnboardingInvitation $invitation, array $acceptedKeys): void
    {
        $session = $this->sessionFor($invitation);
        $required = collect(config('onboarding.policies', []))->pluck('key')->sort()->values();

        if ($required->isEmpty()) {
            throw ValidationException::withMessages(['policies' => 'No policies configured.']);
        }

        $provided = collect($acceptedKeys)->sort()->values();

        if ($required->toArray() !== $provided->toArray()) {
            throw ValidationException::withMessages(['policies' => 'All mandatory policies must be accepted.']);
        }

        $session->update([
            'policy_accepted_at' => now(),
        ]);
    }

    public function setPassword(OnboardingInvitation $invitation, string $password): void
    {
        $session = $this->sessionFor($invitation);

        if ($session->email_verified_at === null) {
            throw ValidationException::withMessages(['email' => 'Email verification is required.']);
        }

        if ($session->policy_accepted_at === null) {
            throw ValidationException::withMessages(['policies' => 'Policy acceptance is required.']);
        }

        $session->update([
            'password_set_at' => now(),
            'password_hash' => Hash::make($password),
        ]);
    }

    public function complete(OnboardingInvitation $invitation, string $password): array
    {
        $session = $this->sessionFor($invitation);

        if ($session->email_verified_at === null || $session->policy_accepted_at === null) {
            throw ValidationException::withMessages(['onboarding' => 'Onboarding steps incomplete.']);
        }

        if (! Hash::check($password, (string) $session->getAttribute('password_hash'))) {
            throw ValidationException::withMessages(['password' => 'Password confirmation failed.']);
        }

        $result = $this->provisioningOrchestrator->provision(
            invitation: $invitation,
            password: $password,
            session: $session,
        );

        $session->update(['completed_at' => now()]);
        $this->invitationService->markUsed($invitation);
        $this->otpService->consumeVerified($invitation->email);

        $invitation->loadMissing('lead');

        if ($invitation->lead !== null) {
            $this->leadService->markConverted($invitation->lead);
        }

        $product = Product::from($invitation->product->value ?? $invitation->product);
        $loginUrl = $product->loginUrl();
        $recipientName = $invitation->lead?->name ?? $invitation->company_name ?? 'there';

        Notification::route('mail', $invitation->email)
            ->notify(new OnboardingWelcomeNotification($recipientName, $loginUrl, $product->label()));

        return [
            'success' => true,
            'login_url' => $loginUrl,
            'provisioning' => $result,
        ];
    }

    private function sessionFor(OnboardingInvitation $invitation): OnboardingSession
    {
        $session = $invitation->session;

        if ($session === null) {
            $session = OnboardingSession::create([
                'onboarding_invitation_id' => $invitation->id,
            ]);
            $invitation->setRelation('session', $session);
        }

        return $session;
    }
}
