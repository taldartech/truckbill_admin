<?php

namespace App\Http\Controllers;

use App\Services\PartnerOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PartnerOnboardingWebController extends Controller
{
    public function __construct(
        private readonly PartnerOnboardingService $partnerOnboardingService,
    ) {}

    public function show(string $token): Response
    {
        $invitation = $this->partnerOnboardingService->findActiveByPlainToken($token);

        if ($invitation === null) {
            return Inertia::render('auth/PartnerOnboardingExpired');
        }

        return Inertia::render('auth/PartnerOnboarding', [
            'token' => $token,
            'email' => $invitation->user->email,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->partnerOnboardingService->findActiveByPlainToken($token);

        if ($invitation === null) {
            return redirect()->route('login')->with('error', 'This onboarding link is invalid or has expired.');
        }

        $request->merge([
            'pan_number' => $request->filled('pan_number') ? strtoupper($request->string('pan_number')->toString()) : null,
            'ifsc_code' => $request->filled('ifsc_code') ? strtoupper($request->string('ifsc_code')->toString()) : null,
        ]);

        $data = $request->validate([
            'legal_name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:20', 'regex:/^[0-9]{10}$/'],
            'working_location' => ['nullable', 'string', 'max:255'],
            'current_company_name' => ['nullable', 'string', 'max:255'],
            'pan_number' => ['nullable', 'string', 'size:10', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'aadhar_number' => ['nullable', 'string', 'size:12', 'regex:/^[0-9]{12}$/'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:30'],
            'ifsc_code' => ['nullable', 'string', 'size:11', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->partnerOnboardingService->complete($invitation, $data);

        return redirect()->route('login')->with('success', 'Onboarding complete. You can now log in with your email and password.');
    }
}
