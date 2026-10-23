<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Http\Controllers\Api\ProductSubscriptionController;
use App\Http\Controllers\LeadWebController;
use App\Http\Middleware\HandleCors;
use App\Http\Middleware\VerifyProductSignature;
use App\Models\EmailOtp;
use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use App\Models\PartnerOnboardingInvitation;
use App\Models\PromoCode;
use App\Models\SalesPartnerProfile;
use App\Models\SubscriptionPlanCatalog;
use App\Models\SubscriptionSetting;
use App\Models\User;
use App\Policies\LeadPolicy;
use App\Policies\UserPolicy;
use App\Services\LeadService;
use App\Services\OnboardingOtpService;
use App\Services\OnboardingSessionService;
use App\Services\ProvisioningOrchestrator;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionQuoteService;
use App\Services\SubscriptionSettingsService;
use App\Support\CrmDashboardStats;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('lead api controller covers manager scope, follow up filter, assignment logic and updates', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Sales Manager');

    $rep = User::factory()->create(['manager_id' => $manager->id]);
    $rep->assignRole('Sales Executive');

    $leadForRep = Lead::create([
        'name' => 'Rep Lead',
        'email' => 'replead@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => $rep->id,
        'next_follow_up_at' => now(),
    ]);

    $leadUnassigned = Lead::create([
        'name' => 'Unassigned Lead',
        'email' => 'unassigned@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => null,
    ]);

    // 1. Lines 55..56 & 62: GET leads as Sales Manager with follow_up=due_today
    $res = $this->actingAs($manager, 'sanctum')
        ->getJson('/api/v1/admin/leads?follow_up=due_today');
    $res->assertOk();
    $ids = collect($res->json('data'))->pluck('id');
    expect($ids)->toContain($leadForRep->id);
    expect($ids)->not->toContain($leadUnassigned->id);

    // 2. Line 84: forbidden when user without leads.assign tries to assign
    $resForbidden = $this->actingAs($rep, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'Should Fail Lead',
        'email' => 'fail@example.com',
        'assigned_to' => $manager->id,
    ]);
    $resForbidden->assertStatus(403);

    // 2b. Line 86: store lead as user without leads.assign defaults to self
    $resStore = $this->actingAs($rep, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'Self Assigned Lead',
        'email' => 'self@example.com',
    ]);
    $resStore->assertCreated();
    expect($resStore->json('assigned_to'))->toBe($rep->id);

    // 3. Line 88: store lead as user with leads.assign but omitting assigned_to
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();
    $resStoreSuper = $this->actingAs($superAdmin, 'sanctum')->postJson('/api/v1/admin/leads', [
        'name' => 'Unassigned Lead Two',
        'email' => 'unassigned2@example.com',
    ]);
    $resStoreSuper->assertCreated();
    expect($resStoreSuper->json('assigned_to'))->toBeNull();

    // 4. Lines 134, 141..146, 151..155: update lead with status=lost, reassignment and follow up
    $resUpdate = $this->actingAs($superAdmin, 'sanctum')->putJson("/api/v1/admin/leads/{$leadForRep->id}", [
        'status' => LeadStatus::Lost->value,
        'remarks' => 'Competitor chosen',
        'assigned_to' => $superAdmin->id,
        'next_follow_up_at' => now()->addDays(5)->toDateString(),
    ]);
    $resUpdate->assertOk();
    $fresh = $leadForRep->fresh();
    expect($fresh->status)->toBe(LeadStatus::Lost)
        ->and($fresh->assigned_to)->toBe($superAdmin->id)
        ->and($fresh->next_follow_up_at)->not->toBeNull();

    // 5. Lines 134 & 153: update lead with non-lost status and null follow-up date
    $resUpdateNonLost = $this->actingAs($superAdmin, 'sanctum')->putJson("/api/v1/admin/leads/{$leadForRep->id}", [
        'status' => LeadStatus::Contacted->value,
        'next_follow_up_at' => null,
    ]);
    $resUpdateNonLost->assertOk();
    expect($leadForRep->fresh()->status)->toBe(LeadStatus::Contacted)
        ->and($leadForRep->fresh()->next_follow_up_at)->toBeNull();
});

test('subscription catalog api controller covers identical payload line 50', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    $cat = SubscriptionPlanCatalog::query()->where('product', Product::Broker->value)->firstOrFail();
    $cat->update(['is_active' => false]);

    $payload = [
        'base_price' => (float) $cat->base_price,
        'included_branches' => (int) $cat->included_branches,
        'users_per_branch' => (int) $cat->users_per_branch,
        'included_lr_credits' => (int) $cat->included_lr_credits,
        'included_trucks' => (int) $cat->included_trucks,
        'addon_branch_price' => (float) $cat->addon_branch_price,
        'addon_user_price' => (float) $cat->addon_user_price,
        'addon_truck_price' => (float) $cat->addon_truck_price,
        'is_active' => false,
    ];

    // Patch with identical parameters when catalog is inactive triggers line 50 fallback
    $res = $this->actingAs($superAdmin, 'sanctum')
        ->patchJson('/api/v1/admin/subscription-catalog/broker', $payload);
    $res->assertOk();
    expect($res->json('message'))->toBe('No changes to save.');
});

test('product subscription controller catalog method covers line 18', function (): void {
    $controller = app(ProductSubscriptionController::class);
    $res = $controller->catalog('broker');
    expect($res->getStatusCode())->toBe(200);
    $data = $res->getData(true);
    expect($data['catalog']['product'])->toBe('broker');
});

test('lead web controller covers manager assignee filter, custom sorting, follow up and assignment updates', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('Sales Manager');

    $rep = User::factory()->create(['manager_id' => $manager->id]);
    $rep->assignRole('Sales Executive');

    $lead = Lead::create([
        'name' => 'Web Lead',
        'email' => 'weblead@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => $rep->id,
        'last_action_at' => now()->subDay(),
    ]);

    // 1. Lines 84 & 330: GET /leads as user with assigned lead scope (Sales Executive)
    $resRep = $this->actingAs($rep)->get('/leads');
    $resRep->assertOk();

    // 2. Line 103: GET /leads with sort=next_follow_up_at
    $resSortFollowUp = $this->actingAs($manager)->get('/leads?sort=next_follow_up_at');
    $resSortFollowUp->assertOk();

    // 3. GET /leads as Sales Manager with sort=last_action_at and scalar status param
    $res = $this->actingAs($manager)->get('/leads?sort=last_action_at&direction=asc&status=new');
    $res->assertOk();

    // 4. GET /leads with unsupported sort to hit line 107
    $resSort = $this->actingAs($manager)->get('/leads?sort=invalid_column');
    $resSort->assertOk();

    // 5. Line 317: PATCH /leads/{lead} with same assigned_to
    $resSameAssign = $this->actingAs($manager)->patch("/leads/{$lead->id}", [
        'name' => 'Same Assignee Lead',
        'email' => 'same@example.com',
        'assigned_to' => $rep->id,
    ]);
    $resSameAssign->assertRedirect();

    // 6. Lines 236 & 317: PATCH /leads/{lead} with changed assigned_to and null next_follow_up_at
    $resUpdate = $this->actingAs($manager)->patch("/leads/{$lead->id}", [
        'name' => 'Updated Lead Name',
        'email' => 'updated@example.com',
        'assigned_to' => $manager->id,
        'next_follow_up_at' => null,
    ]);
    $resUpdate->assertRedirect();
    expect($lead->fresh()->assigned_to)->toBe($manager->id);

    // 7. Line 392: filterValues with non-array non-string
    $controller = app(LeadWebController::class);
    $refMethod = new ReflectionMethod($controller, 'filterValues');
    $refMethod->setAccessible(true);
    $req = Request::create('/leads', 'GET');
    $req->merge(['status' => 12345]);
    expect($refMethod->invoke($controller, $req, 'status'))->toBe([]);
});

test('partner onboarding web controller store handles invalid or expired token line 37', function (): void {
    $res = $this->post('/partner-onboarding/invalid-non-existent-token', [
        'legal_name' => 'Test Partner',
        'mobile' => '9876543210',
    ]);
    $res->assertRedirect(route('login'));
    $res->assertSessionHas('error', 'This onboarding link is invalid or has expired.');
});

test('subscription web controller covers settings, promo codes, company quotes and Parchun features', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. GET /subscriptions/settings (lines 111..118)
    $resSettings = $this->actingAs($superAdmin)->get('/subscriptions/settings');
    $resSettings->assertOk();

    // 2. GET /subscriptions/promo-codes (lines 142..145)
    $resPromos = $this->actingAs($superAdmin)->get('/subscriptions/promo-codes');
    $resPromos->assertOk();

    // 3. POST /subscriptions/companies/broker/comp-1/quote with invalid promo code (line 333)
    $resQuoteErr = $this->actingAs($superAdmin)->postJson('/subscriptions/companies/broker/comp-1/quote', [
        'purchase_type' => 'renewal',
        'promo_code' => 'INVALID_COUPON_XYZ',
    ]);
    $resQuoteErr->assertStatus(422);

    // 4. POST /subscriptions/companies/broker/comp-1/quote with valid call (lines 334-335)
    Http::fake([
        '*/api/admin/subscriptions/comp-1/quote' => Http::response(['total_amount' => 5000], 200),
        '*/api/admin/subscriptions/comp-2' => Http::response([
            'id' => 'comp-2',
            'company_name' => 'Parchun Logistics',
            'status' => 'active',
            'plan_name' => 'Standard',
            'starts_on' => now()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
            'max_branches' => 5,
            'max_users' => 10,
        ], 200),
    ]);

    $resQuoteOk = $this->actingAs($superAdmin)->postJson('/subscriptions/companies/broker/comp-1/quote', [
        'purchase_type' => 'renewal',
    ]);
    $resQuoteOk->assertOk();
    expect($resQuoteOk->json('total_amount'))->toBe(5000);

    // 5. GET /subscriptions/companies/parchun/comp-2 to cover Parchun in productFeatures (line 458)
    $resParchun = $this->actingAs($superAdmin)->get('/subscriptions/companies/parchun/comp-2');
    $resParchun->assertOk();
});

test('subscription web controller rethrows unexpected product api exceptions lines 306, 377, 411', function (): void {
    $this->withoutExceptionHandling();
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    Http::fake([
        '*/api/admin/subscriptions/comp-1/offline-payment' => Http::response('Database failure 500', 500),
        '*/api/admin/subscriptions/comp-1/extend-trial' => Http::response('Timeout 500', 500),
        '*/api/admin/subscriptions/comp-1' => Http::response('Internal Server Error 500', 500),
    ]);

    // 1. Line 306: companyUpdate throws non-422
    try {
        $this->actingAs($superAdmin)->patch('/subscriptions/companies/broker/comp-1', [
            'status' => 'active',
        ]);
        $this->fail('Expected RuntimeException was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('500');
    }

    // 2. Line 377: companyOfflinePayment throws non-422
    try {
        $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/offline-payment', [
            'purchase_type' => 'renewal',
            'amount' => 5000,
            'payment_method' => 'bank_transfer',
        ]);
        $this->fail('Expected RuntimeException was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('500');
    }

    // 3. Line 411: companyExtendTrial throws non-422
    try {
        $this->actingAs($superAdmin)->post('/subscriptions/companies/broker/comp-1/extend-trial', [
            'trial_ends_on' => now()->addDays(14)->toDateString(),
            'amount' => 0,
        ]);
        $this->fail('Expected RuntimeException was not thrown');
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toContain('500');
    }
});

test('user web controller covers search, role filter and password update', function (): void {
    $superAdmin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    // 1. GET /users with search and role parameters (lines 36-40, 44)
    $resFilter = $this->actingAs($superAdmin)->get('/users?search=Admin&role=Super+Admin');
    $resFilter->assertOk();

    // 2. PATCH /users/{id} with password to cover line 176
    $targetUser = User::factory()->create();
    $targetUser->assignRole('Support');

    $resUpdate = $this->actingAs($superAdmin)->patch("/users/{$targetUser->id}", [
        'name' => 'Updated Support User',
        'email' => $targetUser->email,
        'role' => 'Support',
        'password' => 'NewSecretPassword123!',
        'password_confirmation' => 'NewSecretPassword123!',
    ]);
    $resUpdate->assertRedirect(route('users.index'));
    expect(Hash::check('NewSecretPassword123!', $targetUser->fresh()->password))->toBeTrue();
});

test('handle cors middleware covers options 204 and allowed origin headers lines 17, 23-26', function (): void {
    $middleware = new HandleCors;

    // OPTIONS request with allowed origin (https://truckbill.in)
    $reqOptions = Request::create('/api/onboarding/check-phone', 'OPTIONS');
    $reqOptions->headers->set('Origin', 'https://truckbill.in');

    $resOptions = $middleware->handle($reqOptions, fn () => response('should not reach', 200));
    expect($resOptions->getStatusCode())->toBe(204);
    expect($resOptions->headers->get('Access-Control-Allow-Origin'))->toBe('https://truckbill.in');
    expect($resOptions->headers->get('Access-Control-Allow-Methods'))->toContain('GET, POST');

    // GET request with allowed origin
    $reqGet = Request::create('/api/onboarding/check-phone', 'GET');
    $reqGet->headers->set('Origin', 'https://truckbill.in');

    $resGet = $middleware->handle($reqGet, fn () => response('ok', 200));
    expect($resGet->getStatusCode())->toBe(200);
    expect($resGet->headers->get('Access-Control-Allow-Origin'))->toBe('https://truckbill.in');
});

test('verify product signature handles route param that is already a product enum instance line 18', function (): void {
    $middleware = new VerifyProductSignature;
    $request = Request::create('/api/v1/products/broker/subscription-catalog', 'GET');
    $request->setRouteResolver(fn () => new class
    {
        public function parameter($key, $default = null)
        {
            return Product::Broker;
        }
    });

    $timestamp = (string) time();
    $signature = hash_hmac('sha256', $timestamp.'.', Product::Broker->signingSecret());
    $request->headers->set('X-Timestamp', $timestamp);
    $request->headers->set('X-Signature', $signature);

    $response = $middleware->handle($request, fn () => response('ok'));
    expect($response->getContent())->toBe('ok');
});

test('model methods isComplete, isActive, appliesToProduct, hasCompletedPartnerOnboarding and status without eager loading', function (): void {
    // 1. OnboardingSession::isComplete (line 44)
    $session = new OnboardingSession(['completed_at' => now()]);
    expect($session->isComplete())->toBeTrue();
    $sessionIncomplete = new OnboardingSession(['completed_at' => null]);
    expect($sessionIncomplete->isComplete())->toBeFalse();

    // 2. PartnerOnboardingInvitation::isActive (line 50)
    $invActive = new PartnerOnboardingInvitation([
        'completed_at' => null,
        'expires_at' => now()->addDay(),
    ]);
    expect($invActive->isActive())->toBeTrue();
    $invInactive = new PartnerOnboardingInvitation([
        'completed_at' => now(),
        'expires_at' => now()->addDay(),
    ]);
    expect($invInactive->isActive())->toBeFalse();

    // 3. PromoCode::appliesToProduct and createdBy (lines 39, 44)
    $admin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();
    $promo = new PromoCode([
        'applicable_products' => ['broker', 'ownfleet'],
        'created_by' => $admin->id,
    ]);
    expect($promo->appliesToProduct('broker'))->toBeTrue();
    expect($promo->appliesToProduct('parchun'))->toBeFalse();
    expect($promo->createdBy())->toBeInstanceOf(BelongsTo::class);

    // 4. User::hasCompletedPartnerOnboarding (line 77)
    $userPartner = User::factory()->create();
    $userPartner->assignRole('Sales Partner');
    expect($userPartner->hasCompletedPartnerOnboarding())->toBeFalse();

    SalesPartnerProfile::create([
        'user_id' => $userPartner->id,
        'legal_name' => 'Acme Partner',
        'mobile' => '9988776655',
        'onboarding_completed_at' => now(),
    ]);
    expect($userPartner->fresh(['salesPartnerProfile'])->hasCompletedPartnerOnboarding())->toBeTrue();

    // 5. User::partnerOnboardingStatus without loaded relation (line 92)
    $unloadedPartner = User::factory()->create();
    $unloadedPartner->assignRole('Sales Partner');
    PartnerOnboardingInvitation::create([
        'user_id' => $unloadedPartner->id,
        'token_hash' => hash('sha256', 'tok123'),
        'expires_at' => now()->addDays(7),
        'created_by' => $unloadedPartner->id,
    ]);
    // Call on fresh user without eager loading partnerOnboardingInvitations
    $freshUser = User::find($unloadedPartner->id);
    expect($freshUser->relationLoaded('partnerOnboardingInvitations'))->toBeFalse();
    expect($freshUser->partnerOnboardingStatus())->toBe('pending');
});

test('policies cover non-viewer creator view, support role update block and user create and view lines 16, 21, 36', function (): void {
    // 1. LeadPolicy view: user with leads.create but NOT leads.view viewing assigned lead (line 21)
    $policy = new LeadPolicy;
    $creatorOnly = User::factory()->create();
    $creatorOnly->givePermissionTo('leads.create');

    $ownLead = Lead::create([
        'name' => 'Creator Lead',
        'email' => 'creator@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => $creatorOnly->id,
    ]);
    $otherLead = Lead::create([
        'name' => 'Other Lead',
        'email' => 'other@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => null,
    ]);

    expect($policy->view($creatorOnly, $ownLead))->toBeTrue();
    expect($policy->view($creatorOnly, $otherLead))->toBeFalse();

    // 2. LeadPolicy update: user has leads.update but has role Support (line 36)
    $supportUser = User::factory()->create();
    $supportUser->assignRole('Support');
    $supportUser->givePermissionTo(['leads.update', 'leads.view']);
    expect($policy->update($supportUser, $ownLead))->toBeFalse();

    // 3. UserPolicy create and view: covers lines 16, 21
    $userPolicy = new UserPolicy;
    $admin = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();
    expect($userPolicy->create($admin))->toBeTrue();
    expect($userPolicy->view($admin, $supportUser))->toBeTrue();
});

test('app service provider covers password reset mail callback and password defaults', function (): void {
    // 1. Password reset notification toMailUsing callback lines 36-47
    $user = User::factory()->make(['name' => 'Alice Doe', 'email' => 'alice@example.com']);
    $notification = new ResetPassword('token-xyz');
    $mail = $notification->toMail($user);
    expect($mail->subject)->toBe('Reset Password Notification');
    expect($mail->viewData['name'])->toBe('Alice Doe');
    expect($mail->viewData['url'])->toContain('token-xyz');

    // 2. Password::defaults() callback lines 48-64
    $ref = new ReflectionClass(Password::class);
    $callback = $ref->getStaticPropertyValue('defaultCallback');
    expect($callback)->toBeCallable();

    app()['env'] = 'testing';
    expect($callback())->toBeNull();

    app()['env'] = 'production';
    expect($callback())->toBeInstanceOf(Password::class);
    app()['env'] = 'testing';
});

test('lead service covers unassigning, clearing product, setting product and custom source formatting', function (): void {
    $service = app(LeadService::class);
    $actor = User::query()->where('email', 'admin@truckbill.in')->firstOrFail();

    $rep = User::factory()->create();
    $lead = Lead::create([
        'name' => 'Service Lead',
        'email' => 'service@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
        'assigned_to' => $rep->id,
        'product' => Product::Broker,
    ]);

    // 1. Unassign lead (line 90)
    $service->assign($lead, null, $actor);
    expect($lead->fresh()->assigned_to)->toBeNull();

    // 2. Clear product (line 120) and set product (line 119)
    $service->updateDetails($lead, ['product' => null], $actor);
    expect($lead->fresh()->product)->toBeNull();

    $service->updateDetails($lead, ['product' => 'broker'], $actor);
    expect($lead->fresh()->product)->toBe(Product::Broker);

    // 3. formatSource default branch and manual (lines 208, 209)
    $refMethod = new ReflectionMethod($service, 'formatSource');
    $refMethod->setAccessible(true);
    expect($refMethod->invoke($service, 'manual'))->toBe('Manual entry');
    expect($refMethod->invoke($service, 'custom_referral_campaign'))->toBe('custom_referral_campaign');
});

test('onboarding otp service max attempts and invalid/expired otp lines 40, 45', function (): void {
    $service = app(OnboardingOtpService::class);
    $email = 'otp-max@example.com';

    // 1. Non-existent OTP record (line 40)
    expect(fn () => $service->verify('unknown@example.com', '123456'))
        ->toThrow(ValidationException::class, 'OTP expired or invalid.');

    // 2. Maximum attempts exceeded (line 45)
    EmailOtp::create([
        'email' => $email,
        'otp_hash' => hash('sha256', 'salt.123456'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 5,
    ]);

    expect(fn () => $service->verify($email, '123456'))
        ->toThrow(ValidationException::class, 'Maximum OTP attempts exceeded.');
});

test('onboarding session service covers incomplete steps, empty policies, wrong password and session initialization', function (): void {
    $service = app(OnboardingSessionService::class);

    $lead = Lead::create([
        'name' => 'Session Lead',
        'email' => 'session@example.com',
        'status' => LeadStatus::New,
        'source' => 'manual',
    ]);
    $invitation = OnboardingInvitation::create([
        'lead_id' => $lead->id,
        'product' => Product::Broker->value,
        'email' => 'session@example.com',
        'token_hash' => hash('sha256', 'tok123'),
        'expires_at' => now()->addDays(3),
        'created_by' => User::factory()->create()->id,
    ]);

    // 1. Lines 130-133: sessionFor initializes session when session is null, and Line 51: empty config policies
    expect($invitation->session)->toBeNull();
    config(['onboarding.policies' => []]);
    expect(fn () => $service->acceptPolicies($invitation, []))
        ->toThrow(ValidationException::class, 'No policies configured.');
    $invitation->refresh();
    expect($invitation->session)->not->toBeNull();

    // 2. Line 88: complete when onboarding steps incomplete
    expect(fn () => $service->complete($invitation, 'AnyPass123!'))
        ->toThrow(ValidationException::class, 'Onboarding steps incomplete.');

    // 3. Line 92: complete with incorrect password confirmation
    config(['onboarding.policies' => [
        ['key' => 'terms', 'label' => 'Terms of Service', 'version' => '1.0'],
    ]]);
    $session = $invitation->session;
    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('CorrectPassword123!'),
    ]);

    expect(fn () => $service->complete($invitation, 'WrongPassword123!'))
        ->toThrow(ValidationException::class, 'Password confirmation failed.');
});

test('provisioning orchestrator summarize response body covers JSON message, errors and plain text lines 133-141', function (): void {
    $orchestrator = app(ProvisioningOrchestrator::class);
    $refMethod = new ReflectionMethod($orchestrator, 'summarizeResponseBody');
    $refMethod->setAccessible(true);

    // 1. JSON with message
    $msg = $refMethod->invoke($orchestrator, json_encode(['message' => 'Tenant subdomain unavailable.']));
    expect($msg)->toBe('Tenant subdomain unavailable.');

    // 2. JSON with errors object
    $errors = $refMethod->invoke($orchestrator, json_encode([
        'errors' => [
            'email' => ['Email already registered.'],
            'subdomain' => ['Subdomain is reserved.'],
        ],
    ]));
    expect($errors)->toContain('Email already registered.')
        ->and($errors)->toContain('Subdomain is reserved.');

    // 3. Plain text response (line 141)
    $plain = $refMethod->invoke($orchestrator, 'Plain text internal error occurred.');
    expect($plain)->toBe('Plain text internal error occurred.');
});

test('subscription services and stats cover missing catalog 404, grace period, empty meta and null user stats', function (): void {
    $catService = app(SubscriptionCatalogService::class);
    $quoteService = app(SubscriptionQuoteService::class);
    $settingsService = app(SubscriptionSettingsService::class);

    // 1. SubscriptionCatalogService::catalogPayload missing product 404 (line 67)
    expect(fn () => $catService->catalogPayload('non_existent_product'))
        ->toThrow(HttpException::class);

    // 2. SubscriptionQuoteService::estimate missing catalog 404 (line 32)
    SubscriptionPlanCatalog::query()->delete();
    expect(fn () => $quoteService->estimate(['product' => 'broker']))
        ->toThrow(HttpException::class);

    // 3. SubscriptionSettingsService gracePeriodDays and gstRatePercent (lines 28, 34)
    expect($settingsService->gracePeriodDays())->toBeGreaterThanOrEqual(0);
    expect($settingsService->gstRatePercent())->toBeGreaterThanOrEqual(0.0);

    // 4. SubscriptionSettingsService currentSettings & currentMeta when table is empty (lines 21, 39)
    SubscriptionSetting::query()->delete();
    $defaults = $settingsService->currentSettings();
    expect($defaults)->toHaveKey('grace_period_days');

    $meta = $settingsService->currentMeta();
    expect($meta['effective_from'])->toBeNull()
        ->and($meta['updated_by'])->toBeNull();

    // 5. CrmDashboardStats::build(null) covers line 46
    $stats = CrmDashboardStats::build(null);
    expect($stats['follow_up']['overdue'])->toBe(0)
        ->and($stats['follow_up']['due_today'])->toBe(0)
        ->and($stats['follow_up']['upcoming'])->toBe(0);
});
