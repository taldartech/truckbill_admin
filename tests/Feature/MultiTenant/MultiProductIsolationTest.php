<?php

use App\Enums\Product;
use App\Models\User;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionQuoteService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.products.broker.signing_secret' => 'broker-secret-abc']);
    config(['crm.products.ownfleet.signing_secret' => 'fleet-secret-xyz']);
    config(['crm.products.parchun.signing_secret' => 'parchun-secret-123']);
    config(['crm.products.broker.api_url' => 'https://live.truckbill.in']);
    config(['crm.products.ownfleet.api_url' => 'https://fleet.truckbill.in']);
    config(['crm.products.parchun.api_url' => 'https://parchun.truckbill.in']);
});

test('broker signing secret cannot be used to authenticate requests to ownfleet or parchun', function () {
    $timestamp = (string) time();
    $body = json_encode(['code' => 'ANY']);

    // Sign with Broker secret
    $brokerSignature = hash_hmac('sha256', $timestamp.'.'.$body, 'broker-secret-abc');

    // Attempt to access OwnFleet endpoint with Broker signature
    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $brokerSignature,
    ])->postJson('/api/v1/products/ownfleet/promo-codes/validate', ['code' => 'ANY'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid signature.');

    // Attempt to access Parchun endpoint with Broker signature
    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $brokerSignature,
    ])->postJson('/api/v1/products/parchun/promo-codes/validate', ['code' => 'ANY'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid signature.');
});

test('product catalog specifications maintain strict domain boundary isolation', function () {
    $catalogService = app(SubscriptionCatalogService::class);

    $broker = $catalogService->catalogForProduct('broker');
    $fleet = $catalogService->catalogForProduct('ownfleet');
    $parchun = $catalogService->catalogForProduct('parchun');

    // Broker has LR credits, no trucks
    expect($broker->included_lr_credits)->toBe(3600)
        ->and($broker->included_trucks)->toBe(0)
        ->and((float) $broker->addon_truck_price)->toBe(0.0);

    // Fleet has truck limits and truck add-on pricing, no LR credits
    expect($fleet->included_trucks)->toBe(50)
        ->and($fleet->included_lr_credits)->toBe(0)
        ->and((float) $fleet->addon_truck_price)->toBe(365.0);

    // Parchun has neither truck limits nor LR credits
    expect($parchun->included_trucks)->toBe(0)
        ->and($parchun->included_lr_credits)->toBe(0);
});

test('feature flags isolate add-on pricing between fleet assets and broker credits', function () {
    $quoteService = app(SubscriptionQuoteService::class);

    // Requesting LR credits on OwnFleet is suppressed by product feature flags
    $fleetQuote = $quoteService->estimate([
        'product' => 'ownfleet',
        'addon_trucks' => 5,
        'addon_lr_credits' => 5000, // Should be ignored
    ]);
    expect($fleetQuote['quantities']['addon_trucks'])->toBe(5)
        ->and($fleetQuote['quantities']['addon_lr_credits'])->toBe(0);

    // Requesting trucks on Broker is suppressed by product feature flags
    $brokerQuote = $quoteService->estimate([
        'product' => 'broker',
        'addon_trucks' => 20, // Should be ignored
        'addon_lr_credits' => 2000,
    ]);
    expect($brokerQuote['quantities']['addon_trucks'])->toBe(0)
        ->and($brokerQuote['quantities']['addon_lr_credits'])->toBe(2000);
});

test('company update requests are strictly routed to the corresponding product api domain', function () {
    Http::fake([
        'https://fleet.truckbill.in/api/admin/subscriptions/88' => Http::response(['status' => 'updated'], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // Updating OwnFleet company 88
    $this->actingAs($admin)
        ->patch(route('subscriptions.companies.update', ['product' => 'ownfleet', 'id' => 88]), [
            'addon_trucks' => 15,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Subscription updated.');

    // Verify it sent exclusively to fleet.truckbill.in with fleet secret signature
    Http::assertSent(function ($request) {
        $isFleetUrl = str_contains($request->url(), 'https://fleet.truckbill.in/api/admin/subscriptions/88');
        $hasFleetSig = $request->hasHeader('X-Signature');

        return $isFleetUrl && $hasFleetSig;
    });

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'live.truckbill.in'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'parchun.truckbill.in'));
});
