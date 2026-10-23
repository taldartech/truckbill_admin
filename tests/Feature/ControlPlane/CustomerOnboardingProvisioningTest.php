<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Jobs\RetryProvisioningJob;
use App\Models\EmailOtp;
use App\Models\Lead;
use App\Models\ProvisioningLog;
use App\Models\User;
use App\Notifications\OnboardingOtpNotification;
use App\Services\OnboardingInvitationService;
use App\Services\ProvisioningOrchestrator;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.website_api_key' => 'secret-test-key']);
    config(['crm.products.broker.signing_secret' => 'broker-secret']);
    config(['crm.products.broker.api_url' => 'https://live.truckbill.in']);
});

test('website lead ingestion creates new lead when valid api key is supplied', function () {
    Notification::fake();

    $response = $this->withHeader('X-Api-Key', 'secret-test-key')
        ->postJson('/api/v1/public/leads', [
            'name' => 'Amit Sharma',
            'company_name' => 'Sharma Logistics',
            'mobile' => '9876543210',
            'email' => 'amit@sharmalogistics.com',
            'product_interested_in' => 'broker',
            'source' => 'website_demo',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'remarks' => 'Need full brokerage and bilty setup',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['lead_id']);

    $lead = Lead::query()->where('email', 'amit@sharmalogistics.com')->first();
    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Amit Sharma')
        ->and($lead->company_name)->toBe('Sharma Logistics')
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->product)->toBe(Product::Broker);
});

test('website lead ingestion is rejected with 401 when api key is missing or invalid', function () {
    $this->postJson('/api/v1/public/leads', [
        'name' => 'Intruder',
        'email' => 'hacker@example.com',
        'source' => 'unknown',
    ])->assertStatus(401);

    $this->withHeader('X-Api-Key', 'wrong-key')
        ->postJson('/api/v1/public/leads', [
            'name' => 'Intruder',
            'email' => 'hacker@example.com',
            'source' => 'unknown',
        ])->assertStatus(401);
});

test('crm admin can generate an onboarding invitation with product selection', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $lead = Lead::create([
        'name' => 'Rohan Logistics',
        'email' => 'rohan@example.com',
        'mobile' => '9812345678',
        'company_name' => 'Rohan Logistics LLP',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $service = app(OnboardingInvitationService::class);
    $result = $service->createForLead($lead, 'broker', $admin->id);

    expect($result)->toHaveKeys(['plain_token', 'invitation', 'url'])
        ->and($result['invitation']->email)->toBe('rohan@example.com')
        ->and($result['invitation']->product)->toBe(Product::Broker)
        ->and($result['invitation']->expires_at->isFuture())->toBeTrue();

    // Verify public onboarding token endpoint returns valid session state
    $res = $this->getJson('/api/v1/public/onboarding/'.$result['plain_token']);
    $res->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('invitation.email', 'rohan@example.com')
        ->assertJsonPath('invitation.product', 'broker')
        ->assertJsonPath('session.email_verified', false)
        ->assertJsonPath('session.policies_accepted', false);
});

test('public onboarding endpoints handle email otp lifecycle', function () {
    Notification::fake();

    $lead = Lead::create([
        'name' => 'OTP Customer',
        'email' => 'otp-test@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $token = $invite['plain_token'];

    // 1. Send OTP
    $this->postJson("/api/v1/public/onboarding/{$token}/otp/send")
        ->assertOk()
        ->assertJsonPath('success', true);

    Notification::assertSentOnDemand(OnboardingOtpNotification::class);

    $otpRecord = EmailOtp::query()->where('email', 'otp-test@example.com')->first();
    expect($otpRecord)->not->toBeNull()
        ->and($otpRecord->verified_at)->toBeNull();

    // 2. Reject wrong OTP
    $this->postJson("/api/v1/public/onboarding/{$token}/otp/verify", ['otp' => '000000'])
        ->assertStatus(422);

    // 3. Verify with correct OTP
    // Manually simulate known OTP
    $knownOtp = '654321';
    $otpRecord->update([
        'otp_hash' => hash_hmac('sha256', 'otp-test@example.com|'.$knownOtp, (string) config('app.key')),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->postJson("/api/v1/public/onboarding/{$token}/otp/verify", ['otp' => $knownOtp])
        ->assertOk()
        ->assertJsonPath('message', 'Email verified.');

    expect($otpRecord->fresh()->verified_at)->not->toBeNull();
});

test('public onboarding accepts policies and sets compliant password', function () {
    $lead = Lead::create([
        'name' => 'Policy Customer',
        'email' => 'policy@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $token = $invite['plain_token'];

    // Accept policies
    $this->postJson("/api/v1/public/onboarding/{$token}/policy/accept", [
        'accepted' => ['terms_and_conditions', 'privacy_policy', 'refund_policy', 'acceptable_use_policy'],
    ])->assertOk()->assertJsonPath('success', true);

    $invite['invitation']->session->update(['email_verified_at' => now()]);

    // Set valid password
    $this->postJson("/api/v1/public/onboarding/{$token}/password", [
        'password' => 'StrongP@ssw0rd2026',
        'password_confirmation' => 'StrongP@ssw0rd2026',
    ])->assertOk()->assertJsonPath('message', 'Password saved.');

    $session = $invite['invitation']->session->fresh();
    expect($session->policy_accepted_at)->not->toBeNull()
        ->and($session->password_hash)->not->toBeNull();
});

test('provisioning orchestrator executes hmac-signed provisioning and creates provisioning log', function () {
    Notification::fake();

    Http::fake([
        'https://live.truckbill.in/api/onboarding/create-user' => Http::response([
            'status' => 'created',
            'user_id' => 99,
            'company_id' => 42,
        ], 200),
    ]);

    $lead = Lead::create([
        'name' => 'Vijay Transporters',
        'email' => 'vijay@transporters.in',
        'mobile' => '9888877777',
        'company_name' => 'Vijay Transporters Co',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invite['invitation'];
    $session = $invitation->session;

    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('SecretPass123!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);
    $response = $orchestrator->provision($invitation, 'SecretPass123!', $session);

    expect($response)->toBe([
        'status' => 'created',
        'user_id' => 99,
        'company_id' => 42,
    ]);

    // Verify HTTP request headers: HMAC signature, Timestamp, and Idempotency-Key
    Http::assertSent(function ($request) use ($invitation) {
        $hasUrl = $request->url() === 'https://live.truckbill.in/api/onboarding/create-user';
        $hasTimestamp = $request->hasHeader('X-Timestamp');
        $hasSignature = $request->hasHeader('X-Signature');
        $hasIdempotency = $request->header('Idempotency-Key')[0] === 'provision-'.$invitation->id;

        return $hasUrl && $hasTimestamp && $hasSignature && $hasIdempotency;
    });

    // Verify ProvisioningLog recorded in admin database
    $log = ProvisioningLog::query()->where('onboarding_invitation_id', $invitation->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('success')
        ->and($log->product)->toBe('broker')
        ->and($log->attempts)->toBe(1)
        ->and($log->response_payload['user_id'])->toBe(99);
});

test('provisioning orchestrator is strictly idempotent and does not repeat successful api calls', function () {
    Http::fake([
        'https://live.truckbill.in/api/onboarding/create-user' => Http::response(['status' => 'created', 'user_id' => 101], 200),
    ]);

    $lead = Lead::create([
        'name' => 'Idempotent Customer',
        'email' => 'idempotent@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invite['invitation'];
    $session = $invitation->session;
    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('Secret123!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);

    // First call executes
    $res1 = $orchestrator->provision($invitation, 'Secret123!', $session);
    expect($res1['user_id'])->toBe(101);

    // Second call should return cached response without firing a new HTTP request
    $res2 = $orchestrator->provision($invitation, 'Secret123!', $session);
    expect($res2['user_id'])->toBe(101);

    Http::assertSentCount(1);
});

test('provisioning failure marks log as failed and dispatches retry job', function () {
    Queue::fake();

    Http::fake([
        'https://live.truckbill.in/api/onboarding/create-user' => Http::response([
            'message' => 'Database connection timeout on product server',
        ], 500),
    ]);

    $lead = Lead::create([
        'name' => 'Failing Customer',
        'email' => 'fail@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invite['invitation'];
    $session = $invitation->session;
    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('Secret123!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);

    try {
        $orchestrator->provision($invitation, 'Secret123!', $session);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('provisioning');
    }

    $log = ProvisioningLog::query()->where('onboarding_invitation_id', $invitation->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->error_message)->toContain('Database connection timeout');

    Queue::assertPushed(RetryProvisioningJob::class, function ($job) use ($log) {
        return $job->provisioningLogId === $log->id;
    });
});
