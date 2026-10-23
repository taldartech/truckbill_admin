<?php

use App\Enums\LeadStatus;
use App\Enums\Product;
use App\Jobs\RetryProvisioningJob;
use App\Models\Lead;
use App\Models\ProvisioningLog;
use App\Models\User;
use App\Services\LeadService;
use App\Services\OnboardingInvitationService;
use App\Services\ProvisioningOrchestrator;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.products.broker.api_url' => 'https://live.truckbill.in']);
    config(['crm.products.broker.signing_secret' => 'broker-secret']);
    config(['crm.products.ownfleet.api_url' => 'https://fleet.truckbill.in']);
    config(['crm.products.ownfleet.signing_secret' => 'fleet-secret']);
    config(['crm.products.parchun.api_url' => 'https://parchun.truckbill.in']);
    config(['crm.products.parchun.signing_secret' => 'parchun-secret']);
});

test('provisioning error handling captures html or json error responses without leaking raw crashes', function () {
    Queue::fake();

    Http::fake([
        '*/api/onboarding/create-user' => Http::response(
            '<html><body>502 Bad Gateway - Product NGINX server error</body></html>',
            502
        ),
    ]);

    $lead = Lead::create([
        'name' => 'Gateway Error Customer',
        'email' => 'gateway@example.com',
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
        'password_hash' => Hash::make('GatewayPass1!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);

    try {
        $orchestrator->provision($invitation, 'GatewayPass1!', $session);
        $this->fail('ValidationException was expected');
    } catch (ValidationException $e) {
        expect($e->errors()['provisioning'][0])->toBe('User provisioning failed. Our team has been notified.');
    }

    $log = ProvisioningLog::query()->where('onboarding_invitation_id', $invitation->id)->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->attempts)->toBe(1)
        ->and($log->error_message)->toContain('HTML error page returned by product API');
});

test('retry provisioning job reconciles a previously failed provisioning', function () {
    Queue::fake();
    Http::fake([
        '*/api/onboarding/create-user' => Http::sequence()
            ->push(['message' => 'Service temporarily unavailable'], 503)
            ->push(['status' => 'created', 'user_id' => 777], 200),
    ]);

    $lead = Lead::create([
        'name' => 'Retry Customer',
        'email' => 'retry@example.com',
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
        'password_hash' => Hash::make('RetrySecret1!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);

    // Initial failed attempt
    try {
        $orchestrator->provision($invitation, 'RetrySecret1!', $session);
    } catch (Throwable) {
        // Expected
    }

    $log = ProvisioningLog::query()->where('onboarding_invitation_id', $invitation->id)->first();
    expect($log->status)->toBe('failed');

    // 2. Execute retry job
    $job = new RetryProvisioningJob($log->id);
    $job->handle($orchestrator, app(LeadService::class));

    // Verify log is reconciled to success
    $log->refresh();
    expect($log->status)->toBe('success')
        ->and($log->error_message)->toBeNull()
        ->and($log->response_payload['user_id'])->toBe(777);

    // Verify lead status is updated to trial
    expect($lead->fresh()->status)->toBe(LeadStatus::Trial);
});

test('retry job safely skips already successful logs without repeating api call', function () {
    Http::fake();

    $lead = Lead::create([
        'name' => 'Done Lead',
        'email' => 'done@example.com',
        'source' => 'manual',
        'status' => LeadStatus::Trial,
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invite['invitation'];

    $log = ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'prov-already-done-'.$invitation->id,
        'product' => 'broker',
        'status' => 'success',
        'attempts' => 1,
    ]);

    $job = new RetryProvisioningJob($log->id);
    $job->handle(app(ProvisioningOrchestrator::class), app(LeadService::class));

    Http::assertNothingSent();
});

test('companies dashboard aggregates multi-product subscriptions and gracefully tolerates individual product api outage', function () {
    // Product::cases() order is: OwnFleet, Parchun, Broker
    Http::fake([
        'https://fleet.truckbill.in/api/admin/subscriptions*' => Http::response('Server Error', 500),
        'https://parchun.truckbill.in/api/admin/subscriptions*' => Http::response([
            'data' => [
                ['id' => 2, 'company_name' => 'Parchun Beta', 'status' => 'trial'],
            ],
        ], 200),
        'https://live.truckbill.in/api/admin/subscriptions*' => Http::response([
            'data' => [
                ['id' => 1, 'company_name' => 'Broker Alpha', 'status' => 'active'],
            ],
        ], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // The endpoint should not crash with 500; it catches the OwnFleet error and displays Parchun & Broker
    $response = $this->actingAs($admin)
        ->get(route('subscriptions.companies'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subscriptions/Companies')
            ->has('companies', 2)
            ->where('companies.0.company_name', 'Parchun Beta')
            ->where('companies.0.product', 'parchun')
            ->where('companies.1.company_name', 'Broker Alpha')
            ->where('companies.1.product', 'broker'));
});

test('company detail view fetches live subscription state from product api and merges with plan catalog', function () {
    Http::fake([
        'https://live.truckbill.in/api/admin/subscriptions/101' => Http::response([
            'id' => 101,
            'company_name' => 'Apex Transporters',
            'status' => 'active',
            'included_branches' => 2,
            'addon_branches' => 1,
            'total_branches' => 3,
            'starts_on' => '2026-01-01',
            'ends_on' => '2027-01-01',
        ], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->get(route('subscriptions.companies.show', ['product' => 'broker', 'id' => 101]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subscriptions/CompanyShow')
            ->where('product', 'broker')
            ->where('subscription.company_name', 'Apex Transporters')
            ->where('subscription.total_branches', 3)
            ->has('catalog'));
});
