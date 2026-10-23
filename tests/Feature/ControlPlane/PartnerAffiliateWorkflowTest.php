<?php

use App\Models\Lead;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Notifications\SalesPartnerWelcomeNotification;
use App\Services\PartnerOnboardingService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('admin can create partner onboarding invitation with secure token and expiration', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $service = app(PartnerOnboardingService::class);

    $partnerUser = User::create([
        'name' => 'New Partner Candidate',
        'email' => 'partner.candidate@example.com',
        'password' => Hash::make('temp-pass'),
    ]);
    $partnerUser->assignRole('Sales Partner');

    $result = $service->createInvitation($partnerUser, $admin->id);

    expect($result)->toHaveKeys(['plain_token', 'invitation', 'url'])
        ->and($result['invitation']->user_id)->toBe($partnerUser->id)
        ->and($result['invitation']->expires_at->isFuture())->toBeTrue()
        ->and($result['invitation']->completed_at)->toBeNull();

    // Verify lookup by plain token finds active invitation
    $found = $service->findActiveByPlainToken($result['plain_token']);
    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($result['invitation']->id);
});

test('creating new partner invitation cancels prior pending invitation', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $service = app(PartnerOnboardingService::class);

    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $first = $service->createInvitation($partner, $admin->id);
    $second = $service->createInvitation($partner, $admin->id);

    // First invitation should now be marked as completed/superseded
    expect($first['invitation']->fresh()->completed_at)->not->toBeNull()
        ->and($second['invitation']->fresh()->completed_at)->toBeNull();

    // Looking up by first plain token returns null
    expect($service->findActiveByPlainToken($first['plain_token']))->toBeNull()
        ->and($service->findActiveByPlainToken($second['plain_token']))->not->toBeNull();
});

test('expired partner invitation cannot be accessed', function () {
    $service = app(PartnerOnboardingService::class);
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $invitationResult = $service->createInvitation($partner);
    $invitation = $invitationResult['invitation'];

    // Backdate expiration
    $invitation->update(['expires_at' => now()->subHours(2)]);

    expect($service->findActiveByPlainToken($invitationResult['plain_token']))->toBeNull();
});

test('partner onboarding completion captures kyc, activates account and logs activity', function () {
    Notification::fake();

    $service = app(PartnerOnboardingService::class);
    $partnerUser = User::create([
        'name' => 'Partner Candidate',
        'email' => 'kyc.partner@example.com',
        'password' => Hash::make('placeholder'),
    ]);
    $partnerUser->assignRole('Sales Partner');

    $invitationResult = $service->createInvitation($partnerUser);
    $invitation = $invitationResult['invitation'];

    $kycData = [
        'legal_name' => 'Kailash Logistics Agency',
        'mobile' => '9822211100',
        'working_location' => 'Indore, Madhya Pradesh',
        'current_company_name' => 'Kailash Agency',
        'pan_number' => 'ABCDE1234F',
        'aadhar_number' => '123456789012',
        'bank_name' => 'HDFC Bank',
        'bank_account_number' => '5010099887766',
        'ifsc_code' => 'HDFC0001234',
        'password' => 'PartnerP@ss2026',
    ];

    $updatedUser = $service->complete($invitation, $kycData);

    // Verify User record updated
    expect($updatedUser->name)->toBe('Kailash Logistics Agency')
        ->and($updatedUser->email_verified_at)->not->toBeNull()
        ->and(Hash::check('PartnerP@ss2026', $updatedUser->password))->toBeTrue();

    // Verify SalesPartnerProfile created
    $profile = SalesPartnerProfile::query()->where('user_id', $updatedUser->id)->first();
    expect($profile)->not->toBeNull()
        ->and($profile->pan_number)->toBe('ABCDE1234F')
        ->and($profile->bank_name)->toBe('HDFC Bank')
        ->and($profile->working_location)->toBe('Indore, Madhya Pradesh')
        ->and($profile->onboarding_completed_at)->not->toBeNull();

    // Verify invitation marked completed
    expect($invitation->fresh()->completed_at)->not->toBeNull();

    // Verify Welcome notification dispatched
    Notification::assertSentTo($updatedUser, SalesPartnerWelcomeNotification::class);
});

test('sales partner role only has access to assigned leads and cannot see other leads', function () {
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();
    $otherUser = User::query()->where('email', 'sales@truckbill.in')->first();

    // Lead assigned to this partner
    $ownLead = Lead::create([
        'name' => 'Partner Own Lead',
        'email' => 'own@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $partner->id,
    ]);

    // Lead assigned to someone else
    $otherLead = Lead::create([
        'name' => 'Other Lead',
        'email' => 'other@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $otherUser->id,
    ]);

    // Partner can view own lead
    $this->actingAs($partner)
        ->get(route('leads.show', $ownLead))
        ->assertOk();

    // Partner cannot view or edit other lead
    $this->actingAs($partner)
        ->get(route('leads.show', $otherLead))
        ->assertForbidden();

    $this->actingAs($partner)
        ->patch(route('leads.update', $otherLead), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('sales partner cannot access admin subscription pricing or settings', function () {
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $this->actingAs($partner)
        ->get(route('subscriptions.pricing'))
        ->assertForbidden();

    $this->actingAs($partner)
        ->get(route('subscriptions.settings'))
        ->assertForbidden();

    $this->actingAs($partner)
        ->get(route('subscriptions.promo-codes'))
        ->assertForbidden();
});
