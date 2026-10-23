<?php

use App\Enums\Product;
use App\Jobs\RetryProvisioningJob;
use App\Models\Lead;
use App\Models\ProvisioningLog;
use App\Services\OnboardingInvitationService;
use App\Services\ProductApiClient;
use App\Services\ProvisioningOrchestrator;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
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
});

test('orchestrator safely handles network timeouts and schedules background recovery', function () {
    Queue::fake();

    // Simulate connection timeout
    Http::fake([
        'https://live.truckbill.in/api/onboarding/create-user' => function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
        },
    ]);

    $lead = Lead::create([
        'name' => 'Timeout Customer',
        'email' => 'timeout@example.com',
        'source' => 'manual',
        'status' => 'qualified',
        'product' => 'broker',
    ]);

    $invite = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1);
    $invitation = $invite['invitation'];
    $session = $invitation->session;
    $session->update([
        'email_verified_at' => now(),
        'policy_accepted_at' => now(),
        'password_hash' => Hash::make('TimeoutPass1!'),
    ]);

    $orchestrator = app(ProvisioningOrchestrator::class);

    try {
        $orchestrator->provision($invitation, 'TimeoutPass1!', $session);
        $this->fail('ValidationException expected on timeout');
    } catch (ValidationException $e) {
        expect($e->errors()['provisioning'][0])->toBe('User provisioning failed. Our team has been notified.');
    }

    $log = ProvisioningLog::query()->where('onboarding_invitation_id', $invitation->id)->first();
    expect($log->status)->toBe('failed')
        ->and($log->error_message)->toContain('Operation timed out');

    // Verify retry job scheduled
    Queue::assertPushed(RetryProvisioningJob::class);
});

test('product api client handles 504 gateway timeout with runtime exception', function () {
    Http::fake([
        'https://live.truckbill.in/api/admin/subscriptions*' => Http::response('504 Gateway Timeout', 504),
    ]);

    $client = app(ProductApiClient::class);

    expect(fn () => $client->get('/api/admin/subscriptions', Product::Broker))
        ->toThrow(RuntimeException::class);
});

test('deployment and routes integrity check passes without errors', function () {
    // Check artisan route:list executes with exit code 0
    $routeExitCode = Artisan::call('route:list');
    expect($routeExitCode)->toBe(0);

    // Check migrate:status executes with exit code 0
    $migrateExitCode = Artisan::call('migrate:status');
    expect($migrateExitCode)->toBe(0);
});
