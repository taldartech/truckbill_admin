<?php

use App\Models\Lead;
use App\Models\OnboardingInvitation;
use App\Models\PolicyVersion;
use App\Services\OnboardingInvitationService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\PolicyVersionSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        PolicyVersionSeeder::class,
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
        'product' => 'truckbill',
    ]);

    $service = app(OnboardingInvitationService::class);
    $result = $service->createForLead($lead, 'truckbill', 1);

    $this->getJson('/api/v1/public/onboarding/'.$result['plain_token'])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('invitation.email', 'jane@example.com');
});

test('crm user can send onboarding invite', function () {
    Notification::fake();

    $admin = \App\Models\User::query()->where('email', 'admin@truckbill.in')->first();
    $lead = Lead::create([
        'name' => 'Invite User',
        'email' => 'invite@example.com',
        'source' => 'manual',
        'status' => 'qualified',
        'product' => 'truckbill',
    ]);

    $this->actingAs($admin)
        ->post(route('leads.invite', $lead), ['product' => 'truckbill'])
        ->assertRedirect();

    expect(OnboardingInvitation::query()->where('lead_id', $lead->id)->exists())->toBeTrue();
});
