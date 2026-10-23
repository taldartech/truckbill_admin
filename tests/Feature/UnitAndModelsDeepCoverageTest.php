<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Jobs\RetryProvisioningJob;
use App\Models\EmailOtp;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\OnboardingInvitation;
use App\Models\OnboardingSession;
use App\Models\PartnerOnboardingInvitation;
use App\Models\ProvisioningLog;
use App\Models\SalesPartnerProfile;
use App\Models\SubscriptionPlanCatalog;
use App\Models\SubscriptionSetting;
use App\Models\User;
use App\Notifications\LeadCapturedNotification;
use App\Notifications\OnboardingOtpNotification;
use App\Services\LeadService;
use App\Services\OnboardingInvitationService;
use App\Services\OnboardingOtpService;
use App\Services\OnboardingSessionService;
use App\Services\PartnerOnboardingService;
use App\Services\ProductApiClient;
use App\Services\ProvisioningOrchestrator;
use App\Services\SubscriptionSettingsService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('model relationships are correctly linked and queryable', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $salesExec = User::query()->where('email', 'sales@truckbill.in')->first();

    // User manager and direct reports
    expect($salesExec->manager->id)->toBe($salesExec->manager_id)
        ->and($admin->directReports)->toBeInstanceOf(Collection::class);

    // Lead & LeadNote
    $lead = Lead::create(['name' => 'Model Lead', 'email' => 'mlead@test.com', 'source' => 'manual', 'status' => 'new', 'assigned_to' => $admin->id]);
    expect($admin->assignedLeads->pluck('id'))->toContain($lead->id);

    $note = LeadNote::create(['lead_id' => $lead->id, 'user_id' => $admin->id, 'body' => 'Test Note']);
    expect($note->lead->id)->toBe($lead->id)
        ->and($note->author->id)->toBe($admin->id);

    // OnboardingInvitation & OnboardingSession & ProvisioningLog
    $inviteResult = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', $admin->id);
    $invitation = $inviteResult['invitation'];

    expect($invitation->lead->id)->toBe($lead->id)
        ->and($invitation->creator->id)->toBe($admin->id)
        ->and($invitation->isActive())->toBeTrue();

    $session = $invitation->session;
    expect($session->invitation->id)->toBe($invitation->id);

    $log = ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'IDEMP-RELATION-1',
        'product' => 'broker',
        'status' => 'pending',
    ]);
    expect($invitation->provisioningLogs->pluck('id'))->toContain($log->id);

    // PartnerOnboardingInvitation & SalesPartnerProfile
    $partnerUser = User::create(['name' => 'Partner User', 'email' => 'partner.u@test.com', 'password' => Hash::make('pass')]);
    $partnerInvite = app(PartnerOnboardingService::class)->createInvitation($partnerUser, $admin->id)['invitation'];
    expect($partnerInvite->user->id)->toBe($partnerUser->id)
        ->and($partnerInvite->creator->id)->toBe($admin->id);

    $profile = SalesPartnerProfile::create([
        'user_id' => $partnerUser->id,
        'legal_name' => 'Partner Legal',
        'mobile' => '9800098000',
    ]);
    expect($profile->user->id)->toBe($partnerUser->id);

    // SubscriptionSetting & SubscriptionPlanCatalog
    $setting = SubscriptionSetting::create([
        'settings' => ['grace_period_days' => 10, 'gst_rate_percent' => 18.0],
        'effective_from' => now()->toDateString(),
        'updated_by' => $admin->id,
    ]);
    expect($setting->updatedBy->id)->toBe($admin->id);

    $catalog = SubscriptionPlanCatalog::query()->first();
    $catalog->update(['updated_by' => $admin->id]);
    expect($catalog->fresh()->updatedBy->id)->toBe($admin->id);
});

test('notifications toMail executions format templates correctly', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // 1. LeadCapturedNotification with referrer host
    $leadWithReferrer = Lead::create([
        'name' => 'Referrer Customer',
        'email' => 'ref@example.com',
        'source' => 'website_demo',
        'status' => 'new',
        'referrer' => 'https://partner-portal.com/referral/link',
    ]);

    $notif1 = new LeadCapturedNotification($leadWithReferrer);
    expect($notif1->via($admin))->toBe(['mail']);
    $mail1 = $notif1->toMail($admin);
    expect($mail1->subject)->toBe('New website lead: Referrer Customer')
        ->and($mail1->viewData['trafficSource'])->toBe('partner-portal.com');

    // 2. LeadCapturedNotification direct
    $directLead = Lead::create([
        'name' => 'Direct Customer',
        'email' => 'direct@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);
    $notif2 = new LeadCapturedNotification($directLead);
    $mail2 = $notif2->toMail($admin);
    expect($mail2->viewData['trafficSource'])->toBe('Direct');

    // 3. OnboardingOtpNotification toMail
    $otpNotif = new OnboardingOtpNotification('987654', 15);
    expect($otpNotif->via($admin))->toBe(['mail']);
    $otpMail = $otpNotif->toMail($admin);
    expect($otpMail->subject)->toBe('Your TruckBill Email Verification OTP')
        ->and($otpMail->viewData['otp'])->toBe('987654');
});

test('lead service edge cases and formatting helpers', function () {
    $service = app(LeadService::class);
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create(['name' => 'Lead Edge', 'email' => 'edge@test.com', 'source' => 'manual', 'status' => 'new']);

    // ensureLostHasRemarks throws ValidationException when empty
    expect(fn () => $service->ensureLostHasRemarks('lost', '', $lead))
        ->toThrow(ValidationException::class);

    // updateStatus with same status returns unchanged model
    $same = $service->updateStatus($lead, LeadStatus::New, $admin);
    expect($same->id)->toBe($lead->id);

    // assign with same assignee returns unchanged model
    $lead->update(['assigned_to' => $admin->id]);
    $sameAssign = $service->assign($lead, $admin->id, $admin);
    expect($sameAssign->id)->toBe($lead->id);

    // updateDetails changing city, state, and clearing product
    $lead->update(['product' => 'broker']);
    $updated = $service->updateDetails($lead, [
        'product' => null,
        'city' => 'Pune',
        'state' => 'Maharashtra',
        'company_name' => 'New Co',
        'remarks' => 'New remarks',
    ], $admin);
    expect($updated->city)->toBe('Pune')
        ->and($updated->product)->toBeNull();

    // updateDetails with no changes returns early
    expect($service->updateDetails($updated, [], $admin)->id)->toBe($lead->id);

    // scheduleFollowUp clearing date and same date
    $scheduled = $service->scheduleFollowUp($lead, Carbon::parse('2026-12-01'), $admin);
    expect($service->scheduleFollowUp($scheduled, Carbon::parse('2026-12-01'), $admin)->id)->toBe($lead->id);
    $cleared = $service->scheduleFollowUp($scheduled, null, $admin);
    expect($cleared->next_follow_up_at)->toBeNull();

    // Source formatting testing
    $sources = [
        'website_contact',
        'website_partner',
        'website_other',
        'fleetmores_com_demo',
        'fleetmores_in_demo',
        'fleetezee_com_demo',
        'fleetezee_in_demo',
        'custom_source',
    ];
    foreach ($sources as $s) {
        $created = $service->create(['name' => "Source {$s}", 'email' => "{$s}@test.com", 'source' => $s, 'status' => 'new']);
        expect($created->source)->toBe($s);
    }
});

test('onboarding otp service validation and consumption helpers', function () {
    $service = app(OnboardingOtpService::class);
    $email = 'otp.helper@test.com';

    expect($service->isEmailVerified($email))->toBeFalse();

    $otp = '123456';
    $record = EmailOtp::create([
        'email' => $email,
        'otp_hash' => hash_hmac('sha256', "{$email}|{$otp}", (string) config('app.key')),
        'expires_at' => now()->addMinutes(15),
        'verified_at' => now(),
    ]);

    expect($service->isEmailVerified($email))->toBeTrue();

    $service->consumeVerified($email);
    // After consumption, expires_at is updated
    expect($record->fresh()->expires_at)->not->toBeNull();
});

test('onboarding session service error validations and fallbacks', function () {
    $service = app(OnboardingSessionService::class);
    $lead = Lead::create(['name' => 'Session Lead', 'email' => 'session@test.com', 'source' => 'manual', 'status' => 'new']);
    $inviteResult = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $inviteResult['invitation'];
    $session = $invitation->session;

    // resolveInvitation with non-existent token throws ValidationException
    expect(fn () => $service->resolveInvitation('invalid-token-1234'))
        ->toThrow(ValidationException::class);

    // acceptPolicies with invalid policies
    expect(fn () => $service->acceptPolicies($invitation, ['only_one_policy']))
        ->toThrow(ValidationException::class);

    // setPassword without email verified throws
    expect(fn () => $service->setPassword($invitation, 'Pass1234!'))
        ->toThrow(ValidationException::class);

    // setPassword without policy accepted throws
    $session->update(['email_verified_at' => now()]);
    expect(fn () => $service->setPassword($invitation, 'Pass1234!'))
        ->toThrow(ValidationException::class);

    // complete without password confirmation matching throws
    $session->update(['policy_accepted_at' => now(), 'password_hash' => Hash::make('CorrectPassword1!')]);
    expect(fn () => $service->complete($invitation, 'WrongPassword1!'))
        ->toThrow(ValidationException::class);
});

test('provisioning orchestrator and retry job error conditions', function () {
    $orchestrator = app(ProvisioningOrchestrator::class);
    $lead = Lead::create(['name' => 'Err Lead', 'email' => 'err@test.com', 'source' => 'manual', 'status' => 'new']);
    $invitation = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1)['invitation'];

    // 1. Retry without session password throws RuntimeException
    $log = ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'IDEMP-ERR-JOB-2',
        'product' => 'broker',
        'status' => 'failed',
    ]);

    expect(fn () => $orchestrator->retry($log))
        ->toThrow(RuntimeException::class);

    // 2. RetryProvisioningJob catch block execution
    Log::shouldReceive('error')
        ->once()
        ->with('Provisioning retry failed', Mockery::any());

    $job = new RetryProvisioningJob($log->id);
    $job->handle($orchestrator, app(LeadService::class));
});

test('subscription settings service metadata and unchanged store', function () {
    $service = app(SubscriptionSettingsService::class);

    // Store identical settings returns null
    $current = $service->currentSettings();
    $res = $service->store($current);
    expect($res)->toBeNull();

    // Meta retrieval
    $meta = $service->currentMeta();
    expect($meta)->toHaveKeys(['effective_from', 'updated_by']);
});

test('product api client rejects unsupported http methods', function () {
    $client = app(ProductApiClient::class);

    $reflection = new ReflectionClass($client);
    $method = $reflection->getMethod('request');

    expect(fn () => $method->invoke($client, 'DELETE', '/path', Product::Broker))
        ->toThrow(InvalidArgumentException::class, 'Unsupported method: DELETE');
});

test('verify product signature middleware handles empty signing secret with 503', function () {
    config(['crm.products.broker.signing_secret' => '']);

    $this->withHeaders([
        'X-Timestamp' => (string) time(),
        'X-Signature' => 'sig',
    ])->getJson('/api/v1/products/broker/subscription-catalog')
        ->assertStatus(503)
        ->assertJsonPath('message', 'Product signing is not configured.');
});
