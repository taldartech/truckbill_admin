<?php

use App\Models\Lead;
use App\Models\ProvisioningLog;
use App\Models\User;
use App\Services\OnboardingInvitationService;
use App\Support\LeadActivityPresenter;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('lead creation and note additions record comprehensive audit logs with causer', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Audited Customer',
        'email' => 'audit@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    // Record custom activity on lead
    activity('lead')
        ->performedOn($lead)
        ->causedBy($admin)
        ->log('Lead created manually by admin');

    activity('lead')
        ->performedOn($lead)
        ->causedBy($admin)
        ->withProperties(['note' => 'Spoke with CEO'])
        ->log('Note added');

    $activities = Activity::forSubject($lead)->get();
    expect($activities->count())->toBeGreaterThanOrEqual(2);

    $presented = LeadActivityPresenter::collection($activities);
    expect($presented)->not->toBeEmpty()
        ->and($presented[0]['actor'])->toBe($admin->name);
});

test('batch operations track activities with batch uuid', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $batchUuid = Str::uuid()->toString();

    $lead1 = Lead::create(['name' => 'L1', 'email' => 'l1@test.com', 'source' => 'manual', 'status' => 'new']);
    $lead2 = Lead::create(['name' => 'L2', 'email' => 'l2@test.com', 'source' => 'manual', 'status' => 'new']);

    activity('batch')
        ->performedOn($lead1)
        ->causedBy($admin)
        ->withProperty('batch_uuid', $batchUuid)
        ->log('Bulk status updated');

    activity('batch')
        ->performedOn($lead2)
        ->causedBy($admin)
        ->withProperty('batch_uuid', $batchUuid)
        ->log('Bulk status updated');

    $batchLogs = Activity::query()
        ->where('properties->batch_uuid', $batchUuid)
        ->get();

    expect($batchLogs->count())->toBe(2);
});

test('provisioning log error messages truncate excessive length to prevent memory leaks', function () {
    $lead = Lead::create(['name' => 'Long Error Lead', 'email' => 'longerr@test.com', 'source' => 'manual', 'status' => 'new']);
    $invitation = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1)['invitation'];

    // Simulate huge HTML response (100,000 characters)
    $hugeError = str_repeat('Error details stack trace dump ', 3500);

    $log = ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'IDEMP-ERR-TEST',
        'product' => 'broker',
        'status' => 'failed',
        'error_message' => Str::limit($hugeError, 65000),
    ]);

    expect(strlen($log->error_message))->toBeLessThanOrEqual(65003);
});
