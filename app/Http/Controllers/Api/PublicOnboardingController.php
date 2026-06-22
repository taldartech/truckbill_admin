<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OnboardingInvitationService;
use App\Services\OnboardingSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PublicOnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingInvitationService $invitationService,
        private readonly OnboardingSessionService $sessionService,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invitation = $this->sessionService->resolveInvitation($token);
        $session = $invitation->session;

        return response()->json([
            'valid' => true,
            'invitation' => [
                'email' => $invitation->email,
                'mobile' => $invitation->mobile,
                'company_name' => $invitation->company_name,
                'product' => $invitation->product,
            ],
            'session' => [
                'email_verified' => $session?->email_verified_at !== null,
                'policies_accepted' => $session?->policy_accepted_at !== null,
                'password_set' => $session?->password_set_at !== null,
                'completed' => $session?->completed_at !== null,
            ],
            'policies' => config('onboarding.policies', []),
        ]);
    }

    public function sendOtp(string $token): JsonResponse
    {
        $invitation = $this->sessionService->resolveInvitation($token);
        $this->sessionService->sendOtp($invitation);

        return response()->json(['success' => true, 'message' => 'OTP sent.']);
    }

    public function verifyOtp(Request $request, string $token): JsonResponse
    {
        $request->validate(['otp' => ['required', 'string', 'size:6']]);
        $invitation = $this->sessionService->resolveInvitation($token);
        $this->sessionService->verifyOtp($invitation, $request->string('otp')->toString());

        return response()->json(['success' => true, 'message' => 'Email verified.']);
    }

    public function acceptPolicy(Request $request, string $token): JsonResponse
    {
        $request->validate(['accepted' => ['required', 'array', 'min:1'], 'accepted.*' => ['string']]);
        $invitation = $this->sessionService->resolveInvitation($token);
        $this->sessionService->acceptPolicies($invitation, $request->input('accepted', []));

        return response()->json(['success' => true, 'message' => 'Policies accepted.']);
    }

    public function setPassword(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $invitation = $this->sessionService->resolveInvitation($token);
        $this->sessionService->setPassword($invitation, $request->string('password')->toString());

        return response()->json(['success' => true, 'message' => 'Password saved.']);
    }

    public function complete(Request $request, string $token): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $invitation = $this->sessionService->resolveInvitation($token);
        $result = $this->sessionService->complete($invitation, $request->string('password')->toString());

        return response()->json($result);
    }
}
