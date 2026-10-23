<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\EmailOtp;
use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use App\Services\OnboardingInvitationService;
use App\Services\OnboardingOtpService;
use App\Services\PartnerOnboardingService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.website_api_key' => 'website-key-e2e']);
    config(['crm.products.broker.api_url' => 'https://live.truckbill.in']);
    config(['crm.products.broker.signing_secret' => 'broker-secret']);
});

test('uat journey 1: complete end-to-end lifecycle from public lead form to product trial provisioning', function () {
    Notification::fake();

    Http::fake([
        'https://live.truckbill.in/api/onboarding/create-user' => Http::response([
            'status' => 'created',
            'user_id' => 505,
            'company_id' => 88,
        ], 200),
    ]);

    // 1. Customer submits lead form on truckbill.in
    $submitRes = $this->withHeader('X-Api-Key', 'website-key-e2e')
        ->postJson('/api/v1/public/leads', [
            'name' => 'Sanjay Singhania',
            'company_name' => 'Singhania Roadlines',
            'mobile' => '9820098200',
            'email' => 'sanjay@singhania.com',
            'product_interested_in' => 'broker',
            'source' => 'website_demo',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ])
        ->assertStatus(201);

    $leadId = $submitRes->json('lead_id');
    $lead = Lead::findOrFail($leadId);
    expect($lead->status)->toBe(LeadStatus::New);

    // Super Admin / Sales Manager assigns lead to Sales Executive
    $salesExec = User::query()->where('email', 'sales@truckbill.in')->first();
    $lead->update(['assigned_to' => $salesExec->id]);

    // 2. Sales Executive logs in, inspects lead in CRM, and adds a note
    $this->actingAs($salesExec)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Leads/Show')->has('lead'));

    $this->actingAs($salesExec)
        ->post(route('leads.notes.store', $lead), [
            'body' => 'Customer confirmed fleet size and requested broker trial invite.',
        ])->assertRedirect();

    // 3. Sales Exec qualifies lead and sends onboarding invitation
    $this->actingAs($salesExec)
        ->patch(route('leads.update', $lead), ['status' => 'qualified'])
        ->assertRedirect();

    $this->actingAs($salesExec)
        ->post(route('leads.invite', $lead), ['product' => 'broker'])
        ->assertRedirect();

    $invitation = OnboardingInvitation::query()->where('lead_id', $lead->id)->firstOrFail();
    $session = $invitation->session;

    // 4. Customer opens onboarding link and sends OTP
    $knownOtp = '789123';
    EmailOtp::create([
        'email' => 'sanjay@singhania.com',
        'otp_hash' => hash_hmac('sha256', 'sanjay@singhania.com|'.$knownOtp, (string) config('app.key')),
        'expires_at' => now()->addMinutes(15),
    ]);

    // Customer enters and verifies OTP
    $token = 'test-token-'.$invitation->id;
    $invitation->update(['token_hash' => hash_hmac('sha256', $token, (string) config('app.key'))]);

    $this->postJson("/api/v1/public/onboarding/{$token}/otp/verify", ['otp' => $knownOtp])
        ->assertOk()
        ->assertJsonPath('success', true);

    // 5. Customer accepts policies
    $this->postJson("/api/v1/public/onboarding/{$token}/policy/accept", [
        'accepted' => ['terms_and_conditions', 'privacy_policy', 'refund_policy', 'acceptable_use_policy'],
    ])->assertOk();

    // 6. Customer sets strong password
    $this->postJson("/api/v1/public/onboarding/{$token}/password", [
        'password' => 'Singhania@2026!',
        'password_confirmation' => 'Singhania@2026!',
    ])->assertOk();

    // 7. Customer completes onboarding -> Provisions user in target product
    $this->postJson("/api/v1/public/onboarding/{$token}/complete", [
        'password' => 'Singhania@2026!',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('provisioning.status', 'created')
        ->assertJsonPath('provisioning.user_id', 505);

    // Verify lead transitioned to trial
    expect($lead->fresh()->status)->toBe(LeadStatus::Trial);

    // Verify welcome email was dispatched
    Notification::assertSentOnDemand(OnboardingWelcomeNotification::class);
});

test('uat journey 2: sales partner invitation, kyc onboarding, and quote generation', function () {
    Notification::fake();

    $salesManager = User::query()->where('email', 'sales.manager@truckbill.in')->first();
    $partnerService = app(PartnerOnboardingService::class);

    // 1. Sales Manager generates partner invite
    $partnerUser = User::create([
        'name' => 'Agent Mahesh',
        'email' => 'mahesh@partner.in',
        'password' => Hash::make('temp-pass'),
    ]);
    $partnerUser->assignRole('Sales Partner');

    $inviteResult = $partnerService->createInvitation($partnerUser, $salesManager->id);
    $plainToken = $inviteResult['plain_token'];

    // 2. Partner opens link, submits KYC and chooses password
    $this->get(route('partner-onboarding.show', ['token' => $plainToken]))
        ->assertOk();

    $this->post(route('partner-onboarding.store', ['token' => $plainToken]), [
        'legal_name' => 'Mahesh Logistics Agency',
        'mobile' => '9811122233',
        'working_location' => 'Ahmedabad, Gujarat',
        'current_company_name' => 'Mahesh Roadlines',
        'pan_number' => 'ABCDE5678G',
        'aadhar_number' => '112233445566',
        'bank_name' => 'State Bank of India',
        'bank_account_number' => '20100200300',
        'ifsc_code' => 'SBIN0001234',
        'password' => 'PartnerSecure2026!',
        'password_confirmation' => 'PartnerSecure2026!',
    ])->assertRedirect();

    // Verify profile persisted and user verified
    $profile = SalesPartnerProfile::query()->where('user_id', $partnerUser->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->pan_number)->toBe('ABCDE5678G');

    // 3. Partner logs in and accesses pricing calculator for a customer quote
    $this->actingAs($partnerUser->fresh())
        ->postJson(route('pricing-calculator.quote'), [
            'product' => 'broker',
            'addon_branches' => 2,
            'addon_users' => 5,
        ])
        ->assertOk()
        ->assertJsonPath('product', 'broker')
        ->assertJsonPath('subtotal', 35500) // 20000 + (2*6500) + (5*500)
        ->assertJsonPath('total_amount', 41890); // 35500 + 18% GST (6390)
});

test('negative testing: invalid inputs, missing fields and invalid states are rejected with validation errors', function () {
    // 1. Missing required fields in lead form
    $this->withHeader('X-Api-Key', 'website-key-e2e')
        ->postJson('/api/v1/public/leads', [
            'name' => '',
            'email' => 'invalid-email',
            'source' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'source']);

    // 2. Weak password rejected in onboarding
    $lead = Lead::create(['name' => 'Bad Pass Lead', 'email' => 'badpass@test.com', 'source' => 'manual', 'status' => 'new']);
    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $token = $invite['plain_token'];
    $invite['invitation']->session->update(['email_verified_at' => now()]);

    $this->postJson("/api/v1/public/onboarding/{$token}/password", [
        'password' => 'weak',
        'password_confirmation' => 'weak',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    // 3. OTP verification fails after exceeding 5 attempts
    $email = 'exceed@test.com';
    $otpRecord = EmailOtp::create([
        'email' => $email,
        'otp_hash' => hash_hmac('sha256', $email.'|123456', (string) config('app.key')),
        'expires_at' => now()->addMinutes(15),
        'attempts' => 5,
    ]);

    $otpService = app(OnboardingOtpService::class);
    expect(fn () => $otpService->verify($email, '123456'))
        ->toThrow(ValidationException::class);
});
