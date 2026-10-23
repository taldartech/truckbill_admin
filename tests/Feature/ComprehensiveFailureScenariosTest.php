<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Models\EmailOtp;
use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use App\Models\PromoCode;
use App\Models\ProvisioningLog;
use App\Models\User;
use App\Services\LeadService;
use App\Services\OnboardingOtpService;
use App\Services\OnboardingSessionService;
use App\Services\PartnerOnboardingService;
use App\Services\ProvisioningOrchestrator;
use App\Services\SubscriptionCatalogService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

/*
|--------------------------------------------------------------------------
| Group 1: Authentication & Authorization Failure Tests (401 & 403)
|--------------------------------------------------------------------------
*/

test('unauthenticated guests are rejected with 401 on admin APIs and 302 on web routes', function (): void {
    // Admin REST APIs require sanctum authentication
    $this->getJson('/api/v1/admin/leads')->assertStatus(401);
    $this->getJson('/api/v1/admin/users')->assertStatus(401);
    $this->getJson('/api/v1/admin/dashboards/crm')->assertStatus(401);
    $this->getJson('/api/v1/admin/subscription-catalog')->assertStatus(401);

    // Admin Web routes redirect unauthenticated guests to login
    $this->get('/leads')->assertRedirect(route('login'));
    $this->get('/users')->assertRedirect(route('login'));
    $this->get('/subscriptions/pricing')->assertRedirect(route('login'));
    $this->get('/subscriptions/companies')->assertRedirect(route('login'));
});

test('users without proper permissions receive 403 Forbidden on protected resources', function (): void {
    $unprivileged = User::factory()->create();
    // No roles or permissions granted

    // Cannot view or create leads
    $this->actingAs($unprivileged, 'sanctum')->getJson('/api/v1/admin/leads')->assertStatus(403);
    $this->actingAs($unprivileged, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'Test Lead',
        'email' => 'test@example.com',
    ])->assertStatus(403);

    // Support role cannot update leads
    $supportUser = User::factory()->create();
    $supportUser->assignRole('Support');
    $lead = Lead::create([
        'name' => 'Active Lead',
        'email' => 'active@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);
    $this->actingAs($supportUser, 'sanctum')
        ->putJson("/api/v1/admin/leads/{$lead->id}", ['status' => LeadStatus::Contacted->value])
        ->assertStatus(403);

    // User without leads.delete cannot delete lead via web
    $this->actingAs($supportUser)->delete("/leads/{$lead->id}")->assertStatus(403);

    // User cannot delete their own account (UserPolicy self-delete guard)
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();
    $this->actingAs($superAdmin)->delete("/users/{$superAdmin->id}")->assertStatus(403);

    // User without roles.manage cannot update roles
    $salesExec = User::factory()->create();
    $salesExec->assignRole('Sales Executive');
    $role = Role::query()->where('name', 'Support')->firstOrFail();
    $this->actingAs($salesExec)->patch("/roles/{$role->id}", [
        'permissions' => ['leads.view'],
    ])->assertStatus(403);

    // User without users.manage cannot create users
    $this->actingAs($salesExec)->post('/users', [
        'name' => 'New Guy',
        'email' => 'newguy@example.com',
        'role' => 'Support',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertStatus(403);

    // User without subscriptions permissions cannot manage subscription settings
    $this->actingAs($salesExec)->patch('/subscriptions/settings', [
        'grace_period_days' => 7,
        'gst_rate_percent' => 18,
    ])->assertStatus(403);

    $this->actingAs($salesExec)->post('/subscriptions/promo-codes', [
        'code' => 'TESTCODE',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
    ])->assertStatus(403);

    $this->actingAs($salesExec)->get('/subscriptions/companies')->assertStatus(403);
});

/*
|--------------------------------------------------------------------------
| Group 2: Input Validation & Negative Input Scenarios (422)
|--------------------------------------------------------------------------
*/

test('lead API rejects invalid inputs with 422 Unprocessable Content', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. Missing required fields
    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/admin/leads', [
        'company_name' => 'Acme Corp',
    ])->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);

    // 2. Invalid email format
    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'John Doe',
        'email' => 'invalid-not-an-email',
    ])->assertStatus(422)->assertJsonValidationErrors(['email']);

    // 3. Invalid product enum
    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'product' => 'non_existent_product',
    ])->assertStatus(422)->assertJsonValidationErrors(['product']);

    // 4. Invalid assignee ID (must exist in users table)
    $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'assigned_to' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors(['assigned_to']);

    // 5. Empty lead note body
    $lead = Lead::create([
        'name' => 'Lead One',
        'email' => 'one@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);
    $this->actingAs($superAdmin, 'sanctum')
        ->postJson("/api/v1/admin/leads/{$lead->id}/notes", ['body' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['body']);

    // 6. Marking as lost without remarks returns 422
    $this->actingAs($superAdmin, 'sanctum')
        ->putJson("/api/v1/admin/leads/{$lead->id}", [
            'status' => LeadStatus::Lost->value,
            'remarks' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['remarks']);
});

test('user management rejects duplicate emails and invalid payloads with 422', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. Duplicate email on creation
    $this->actingAs($superAdmin)->post('/users', [
        'name' => 'Duplicate Admin',
        'email' => 'admin@truckbill.in',
        'role' => 'Admin',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ])->assertSessionHasErrors(['email']);

    // 2. Password mismatch
    $this->actingAs($superAdmin)->post('/users', [
        'name' => 'Mismatch User',
        'email' => 'mismatch@example.com',
        'role' => 'Support',
        'password' => 'Password123!',
        'password_confirmation' => 'DifferentPassword123!',
    ])->assertSessionHasErrors(['password']);

    // 3. Invalid role
    $this->actingAs($superAdmin)->post('/users', [
        'name' => 'Bad Role User',
        'email' => 'badrole@example.com',
        'role' => 'NonExistentRole',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertSessionHasErrors(['role']);
});

test('subscription and promo code creation rejects negative numbers, date order violations, and duplicates', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. Negative discount value
    $this->actingAs($superAdmin)->post('/subscriptions/promo-codes', [
        'code' => 'NEGATIVE10',
        'discount_type' => 'percentage',
        'discount_value' => -15,
        'start_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['discount_value']);

    // 2. End date before start date
    $this->actingAs($superAdmin)->post('/subscriptions/promo-codes', [
        'code' => 'DATEFAIL',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->addDays(10)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ])->assertSessionHasErrors(['end_date']);

    // 3. Duplicate promo code
    PromoCode::create([
        'code' => 'EXISTINGCODE',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->toDateString(),
        'applicable_products' => ['broker'],
        'is_active' => true,
        'created_by' => $superAdmin->id,
    ]);

    $this->actingAs($superAdmin)->post('/subscriptions/promo-codes', [
        'code' => 'EXISTINGCODE',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'start_date' => now()->toDateString(),
    ])->assertSessionHasErrors(['code']);

    // 4. Subscription settings negative grace period or GST > 100%
    $this->actingAs($superAdmin)->patch('/subscriptions/settings', [
        'grace_period_days' => -5,
        'gst_rate_percent' => 150,
    ])->assertSessionHasErrors(['grace_period_days', 'gst_rate_percent']);

    // 5. Offline payment with invalid purchase type or negative amount
    $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/offline-payment', [
        'purchase_type' => 'invalid_type',
        'addon_branches' => -5,
    ])->assertSessionHasErrors(['purchase_type', 'addon_branches']);

    // 6. Extend trial with past date
    $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/extend-trial', [
        'trial_ends_on' => now()->subDay()->toDateString(),
        'amount' => 0,
    ])->assertSessionHasErrors(['trial_ends_on']);
});

test('public lead submission without API key returns 401', function (): void {
    $this->postJson('/api/v1/public/leads', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
    ])->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Group 3: State Machine & Business Rule Failures
|--------------------------------------------------------------------------
*/

test('marking a lead as Lost without remarks triggers validation failure', function (): void {
    $leadService = app(LeadService::class);

    $lead = Lead::create([
        'name' => 'Lost Candidate',
        'email' => 'lost@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);

    // Marking as Lost without remarks or rejection reason must fail
    expect(fn () => $leadService->ensureLostHasRemarks(LeadStatus::Lost->value, '', $lead))
        ->toThrow(ValidationException::class, 'Remarks are required when marking a lead as Lost.');
});

test('OTP verification fails on expired, non-existent or exceeded attempts', function (): void {
    $otpService = app(OnboardingOtpService::class);
    $email = 'otp-tester@example.com';

    // 1. Non-existent OTP
    expect(fn () => $otpService->verify($email, '999999'))
        ->toThrow(ValidationException::class, 'OTP expired or invalid.');

    // 2. Expired OTP
    EmailOtp::create([
        'email' => $email,
        'otp_hash' => hash('sha256', 'salt.123456'),
        'expires_at' => now()->subMinute(),
        'attempts' => 0,
    ]);
    expect(fn () => $otpService->verify($email, '123456'))
        ->toThrow(ValidationException::class, 'OTP expired or invalid.');

    // 3. Invalid OTP increments attempts and throws Invalid OTP
    EmailOtp::query()->delete();
    $validOtp = EmailOtp::create([
        'email' => $email,
        'otp_hash' => hash('sha256', 'salt.123456'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ]);

    expect(fn () => $otpService->verify($email, '000000'))
        ->toThrow(ValidationException::class, 'Invalid OTP.');
    expect($validOtp->fresh()->attempts)->toBe(1);

    // 4. Max attempts exceeded
    $validOtp->update(['attempts' => 5]);
    expect(fn () => $otpService->verify($email, '123456'))
        ->toThrow(ValidationException::class, 'Maximum OTP attempts exceeded.');
});

test('onboarding session enforces strict prerequisite steps before completion', function (): void {
    $service = app(OnboardingSessionService::class);

    $lead = Lead::create([
        'name' => 'Prereq Lead',
        'email' => 'prereq@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);

    $invitation = OnboardingInvitation::create([
        'lead_id' => $lead->id,
        'product' => Product::Broker->value,
        'email' => 'prereq@example.com',
        'token_hash' => hash('sha256', 'token-prereq'),
        'expires_at' => now()->addDays(2),
        'created_by' => User::factory()->create()->id,
    ]);

    // 1. Cannot accept partial policies when mandatory policies are missing
    config(['onboarding.policies' => [
        ['key' => 'terms', 'label' => 'Terms', 'version' => '1.0'],
        ['key' => 'privacy', 'label' => 'Privacy', 'version' => '1.0'],
    ]]);
    expect(fn () => $service->acceptPolicies($invitation, ['terms']))
        ->toThrow(ValidationException::class, 'All mandatory policies must be accepted.');

    // 2. Cannot set password before email verification
    expect(fn () => $service->setPassword($invitation, 'SecureP@ss12345!'))
        ->toThrow(ValidationException::class, 'Email verification is required.');

    // 3. Cannot set password before policy acceptance
    $session = $invitation->session;
    $session->update(['email_verified_at' => now()]);
    expect(fn () => $service->setPassword($invitation, 'SecureP@ss12345!'))
        ->toThrow(ValidationException::class, 'Policy acceptance is required.');

    // 4. Cannot complete onboarding before policy accepted or email verified
    $session->update(['email_verified_at' => null, 'policy_accepted_at' => null]);
    expect(fn () => $service->complete($invitation, 'SecureP@ss12345!'))
        ->toThrow(ValidationException::class, 'Onboarding steps incomplete.');

    // 5. Cannot complete onboarding with mismatched password
    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('ActualPassword123!'),
    ]);
    expect(fn () => $service->complete($invitation, 'WrongPassword123!'))
        ->toThrow(ValidationException::class, 'Password confirmation failed.');
});

test('partner onboarding rejects invalid PAN number format and non-numeric mobile', function (): void {
    $partnerUser = User::factory()->create();
    $partnerUser->assignRole('Sales Partner');
    $admin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    $invite = app(PartnerOnboardingService::class)->createInvitation($partnerUser, $admin->id);
    $plainToken = $invite['plain_token'];

    // 1. Invalid PAN (must be 5 letters, 4 digits, 1 letter)
    $resPan = $this->post("/partner-onboarding/{$plainToken}", [
        'legal_name' => 'Apex Partner',
        'mobile' => '9876543210',
        'pan_number' => 'INVALID_PAN_123',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);
    $resPan->assertSessionHasErrors(['pan_number']);

    // 2. Invalid mobile (must be 10 digits)
    $resMob = $this->post("/partner-onboarding/{$plainToken}", [
        'legal_name' => 'Apex Partner',
        'mobile' => '12345',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]);
    $resMob->assertSessionHasErrors(['mobile']);
});

/*
|--------------------------------------------------------------------------
| Group 4: Promo Code & Coupon Failure Rules
|--------------------------------------------------------------------------
*/

test('promo code validation detects all failure rules and states', function (): void {
    $catalogService = app(SubscriptionCatalogService::class);
    $admin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. Non-existent code
    $resNotFound = $catalogService->validatePromo('broker', 'DOESNOTEXIST');
    expect($resNotFound['valid'])->toBeFalse()
        ->and($resNotFound['message'])->toBe('Invalid promo code.');

    // 2. Inactive code
    $inactive = PromoCode::create([
        'code' => 'INACTIVE10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->subDays(5)->toDateString(),
        'applicable_products' => ['broker'],
        'is_active' => false,
        'created_by' => $admin->id,
    ]);
    $resInactive = $catalogService->validatePromo('broker', 'INACTIVE10');
    expect($resInactive['valid'])->toBeFalse()
        ->and($resInactive['message'])->toBe('Invalid promo code.');

    // 3. Applicable to wrong product
    $wrongProduct = PromoCode::create([
        'code' => 'OWNFLEETONLY',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'start_date' => now()->subDays(5)->toDateString(),
        'applicable_products' => ['ownfleet'],
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $resWrongProd = $catalogService->validatePromo('broker', 'OWNFLEETONLY');
    expect($resWrongProd['valid'])->toBeFalse()
        ->and($resWrongProd['message'])->toBe('Promo code is not valid for this product.');

    // 4. Future start date (not yet active)
    $futureCode = PromoCode::create([
        'code' => 'FUTURE20',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'start_date' => now()->addDays(10)->toDateString(),
        'applicable_products' => ['broker'],
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $resFuture = $catalogService->validatePromo('broker', 'FUTURE20');
    expect($resFuture['valid'])->toBeFalse()
        ->and($resFuture['message'])->toBe('Promo code is not yet active.');

    // 5. Expired code
    $expiredCode = PromoCode::create([
        'code' => 'EXPIRED30',
        'discount_type' => 'percentage',
        'discount_value' => 30,
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'applicable_products' => ['broker'],
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $resExpired = $catalogService->validatePromo('broker', 'EXPIRED30');
    expect($resExpired['valid'])->toBeFalse()
        ->and($resExpired['message'])->toBe('Promo code has expired.');

    // 6. Max uses reached
    $maxedOut = PromoCode::create([
        'code' => 'MAXEDOUT',
        'discount_type' => 'fixed',
        'discount_value' => 500,
        'start_date' => now()->subDays(10)->toDateString(),
        'applicable_products' => ['broker'],
        'max_uses' => 3,
        'uses_count' => 3,
        'is_active' => true,
        'created_by' => $admin->id,
    ]);
    $resMaxed = $catalogService->validatePromo('broker', 'MAXEDOUT');
    expect($resMaxed['valid'])->toBeFalse()
        ->and($resMaxed['message'])->toBe('Promo code usage limit reached.');
});

/*
|--------------------------------------------------------------------------
| Group 5: External API & Provisioning Failures
|--------------------------------------------------------------------------
*/

test('product API returns 422 errors and extracts meaningful messages for web display', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. Offline payment receives 422 from product API with custom message
    Http::fake([
        '*/api/admin/subscriptions/comp-1/offline-payment' => Http::response(
            'Product API error: 422 {"message": "Invoice already settled."}',
            422
        ),
    ]);

    $resOffline = $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/offline-payment', [
        'purchase_type' => 'renewal',
        'amount' => 5000,
    ]);
    $resOffline->assertSessionHasErrors(['offline']);

    // 2. Extend trial receives 422 from product API
    Http::fake([
        '*/api/admin/subscriptions/comp-1/extend-trial' => Http::response(
            'Product API error: 422 {"message": "Maximum trial extensions reached for this tenant."}',
            422
        ),
    ]);

    $resExtend = $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/extend-trial', [
        'trial_ends_on' => now()->addDays(7)->toDateString(),
        'amount' => 0,
    ]);
    $resExtend->assertSessionHasErrors(['extend_trial']);
});

test('provisioning orchestrator retry fails fast when session password hash is missing', function (): void {
    $lead = Lead::create([
        'name' => 'No Password Lead',
        'email' => 'nopass@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);

    $invitation = OnboardingInvitation::create([
        'lead_id' => $lead->id,
        'product' => Product::Broker->value,
        'email' => 'nopass@example.com',
        'token_hash' => hash('sha256', 'nopass-token'),
        'expires_at' => now()->addDays(3),
        'created_by' => User::factory()->create()->id,
    ]);

    OnboardingSession::create([
        'onboarding_invitation_id' => $invitation->id,
        'password_hash' => null, // Password was never set
    ]);

    $log = ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'prov-missing-pass',
        'product' => Product::Broker->value,
        'status' => 'failed',
        'attempts' => 1,
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);
    expect(fn () => $orchestrator->retry($log))
        ->toThrow(RuntimeException::class, 'Cannot retry provisioning without session password.');
});
