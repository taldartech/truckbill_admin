<?php

use App\Models\PartnerOnboardingInvitation;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Notifications\PartnerOnboardingInvitationNotification;
use App\Notifications\SalesPartnerWelcomeNotification;
use App\Services\PartnerOnboardingService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('admin can create sales partner with onboarding invitation', function () {
    Notification::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Partner Pending',
            'email' => 'pending.partner@truckbill.in',
            'role' => 'Sales Partner',
            'skip_onboarding' => false,
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'pending.partner@truckbill.in')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('Sales Partner'))->toBeTrue()
        ->and(PartnerOnboardingInvitation::query()->where('user_id', $user->id)->exists())->toBeTrue();

    Notification::assertSentOnDemand(PartnerOnboardingInvitationNotification::class, function (PartnerOnboardingInvitationNotification $notification): bool {
        $mail = $notification->toMail(new stdClass);
        $data = $mail->viewData;

        return str_contains((string) ($data['url'] ?? ''), '/partner-onboarding/');
    });
});

test('admin can create sales partner with skipped onboarding', function () {
    Notification::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Partner Skipped',
            'email' => 'skipped.partner@truckbill.in',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
            'role' => 'Sales Partner',
            'skip_onboarding' => true,
        ])
        ->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'skipped.partner@truckbill.in')->first();

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password123!', $user->password))->toBeTrue()
        ->and(PartnerOnboardingInvitation::query()->where('user_id', $user->id)->exists())->toBeFalse();

    Notification::assertNothingSent();
});

test('sales partner can complete onboarding via token', function () {
    Notification::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $user = User::factory()->create([
        'name' => 'Partner To Complete',
        'email' => 'complete.partner@truckbill.in',
        'email_verified_at' => null,
    ]);
    $user->assignRole('Sales Partner');

    $result = app(PartnerOnboardingService::class)->createInvitation($user, $admin->id);
    $plainToken = $result['plain_token'];
    $invitation = $result['invitation'];

    $this->get(route('partner-onboarding.show', ['token' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/PartnerOnboarding')
            ->where('email', 'complete.partner@truckbill.in'));

    $this->post(route('partner-onboarding.store', ['token' => $plainToken]), [
        'legal_name' => 'Partner Legal Name',
        'mobile' => '9876543210',
        'working_location' => 'Mumbai',
        'current_company_name' => 'Acme Logistics',
        'pan_number' => 'ABCDE1234F',
        'aadhar_number' => '123456789012',
        'bank_name' => 'HDFC Bank',
        'bank_account_number' => '1234567890',
        'ifsc_code' => 'HDFC0001234',
        'password' => 'PartnerPass123!',
        'password_confirmation' => 'PartnerPass123!',
    ])
        ->assertRedirect(route('login'));

    $user->refresh();
    $profile = SalesPartnerProfile::query()->where('user_id', $user->id)->first();

    expect($user->name)->toBe('Partner Legal Name')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('PartnerPass123!', $user->password))->toBeTrue()
        ->and($profile)->not->toBeNull()
        ->and($profile->legal_name)->toBe('Partner Legal Name')
        ->and($profile->mobile)->toBe('9876543210')
        ->and($profile->onboarding_completed_at)->not->toBeNull()
        ->and($invitation->fresh()->completed_at)->not->toBeNull();

    Notification::assertSentTo($user, SalesPartnerWelcomeNotification::class, function (SalesPartnerWelcomeNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        return $mail->replyTo[0][0] === config('partner_onboarding.documents_reply_to');
    });
});

test('onboarding requires legal name and mobile', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $user = User::factory()->create([
        'email' => 'invalid.partner@truckbill.in',
        'email_verified_at' => null,
    ]);
    $user->assignRole('Sales Partner');

    $result = app(PartnerOnboardingService::class)->createInvitation($user, $admin->id);

    $this->from(route('partner-onboarding.show', ['token' => $result['plain_token']]))
        ->post(route('partner-onboarding.store', ['token' => $result['plain_token']]), [
            'legal_name' => '',
            'mobile' => '',
            'password' => 'PartnerPass123!',
            'password_confirmation' => 'PartnerPass123!',
        ])
        ->assertSessionHasErrors(['legal_name', 'mobile']);
});

test('expired onboarding token shows expired page', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $user = User::factory()->create(['email' => 'expired.partner@truckbill.in']);
    $user->assignRole('Sales Partner');

    $service = app(PartnerOnboardingService::class);
    $result = $service->createInvitation($user, $admin->id);

    PartnerOnboardingInvitation::query()
        ->where('user_id', $user->id)
        ->update(['expires_at' => now()->subDay()]);

    $this->get(route('partner-onboarding.show', ['token' => $result['plain_token']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/PartnerOnboardingExpired'));
});

test('non sales partner creation still requires password', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => 'Sales Rep',
            'email' => 'rep2@truckbill.in',
            'role' => 'Sales Executive',
            'skip_onboarding' => false,
        ])
        ->assertSessionHasErrors('password');
});
