<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\User;
use App\Notifications\OnboardingInvitationNotification;
use App\Notifications\OnboardingWelcomeNotification;
use App\Services\OnboardingInvitationService;
use App\Services\OnboardingSessionService;
use App\Services\ProvisioningOrchestrator;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
    ]);
});

test('onboarding token validation returns invitation details', function () {
    $lead = Lead::create([
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'mobile' => '9876543210',
        'company_name' => 'Jane Co',
        'source' => 'manual',
        'status' => 'qualified',
        'product' => 'broker',
    ]);

    $service = app(OnboardingInvitationService::class);
    $result = $service->createForLead($lead, 'broker', 1);

    $this->getJson('/api/v1/public/onboarding/'.$result['plain_token'])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('invitation.email', 'jane@example.com');
});

test('crm user can send onboarding invite', function () {
    Notification::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $lead = Lead::create([
        'name' => 'Invite User',
        'email' => 'invite@example.com',
        'source' => 'manual',
        'status' => 'qualified',
        'product' => 'broker',
    ]);

    $this->actingAs($admin)
        ->post(route('leads.invite', $lead), ['product' => 'broker'])
        ->assertRedirect();

    Notification::assertSentOnDemand(OnboardingInvitationNotification::class, function (OnboardingInvitationNotification $notification) use ($lead): bool {
        $mail = $notification->toMail(new \stdClass);
        $data = $mail->viewData;

        return $data['name'] === $lead->name
            && str_contains((string) ($data['url'] ?? ''), '/onboarding/');
    });

    expect(OnboardingInvitation::query()->where('lead_id', $lead->id)->exists())->toBeTrue();
});

test('completing onboarding converts the linked lead', function () {
    Notification::fake();

    $this->mock(ProvisioningOrchestrator::class, function ($mock): void {
        $mock->shouldReceive('provision')
            ->once()
            ->andReturn(['status' => 'created', 'user_id' => 1]);
    });

    $lead = Lead::create([
        'name' => 'Convert Me',
        'email' => 'convert@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Qualified,
        'product' => 'broker',
    ]);

    $invitationResult = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invitationResult['invitation'];
    $session = $invitation->session;

    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('Password1!'),
    ]);

    app(OnboardingSessionService::class)->complete($invitation->fresh(['lead', 'session']), 'Password1!');

    Notification::assertSentOnDemand(OnboardingWelcomeNotification::class, function (OnboardingWelcomeNotification $notification): bool {
        $mail = $notification->toMail(new \stdClass);
        $data = $mail->viewData;

        return $data['name'] === 'Convert Me'
            && str_contains((string) ($data['loginUrl'] ?? ''), '/login');
    });

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Converted)
        ->and($lead->converted_at)->not->toBeNull();
});
