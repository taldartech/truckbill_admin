<?php

use App\Models\PromoCode;
use App\Models\SubscriptionPlanCatalog;
use App\Models\User;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionSettingsService;
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
});

test('updating subscription catalog creates new version with effective_from date without altering historical version', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $catalogService = app(SubscriptionCatalogService::class);

    $original = $catalogService->catalogForProduct('broker');
    $originalPrice = (float) $original->base_price;

    // Admin updates broker pricing
    $this->actingAs($admin)
        ->patch(route('subscriptions.pricing.update', ['product' => 'broker']), [
            'base_price' => 24000.00,
            'included_branches' => 3,
            'users_per_branch' => 4,
            'included_lr_credits' => 4000,
            'included_trucks' => 0,
            'addon_branch_price' => 7000.00,
            'addon_user_price' => 600.00,
            'addon_truck_price' => 0,
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Pricing updated.');

    // Verify active catalog now points to new version
    $updated = $catalogService->catalogForProduct('broker');
    expect((float) $updated->base_price)->toBe(24000.0)
        ->and($updated->included_branches)->toBe(3)
        ->and($updated->id)->not->toBe($original->id);

    // Verify historical version still exists in database
    $historyCount = SubscriptionPlanCatalog::query()->where('product', 'broker')->count();
    expect($historyCount)->toBeGreaterThanOrEqual(2);
});

test('submitting identical catalog attributes is a no-op and does not create redundant version rows', function () {
    $catalogService = app(SubscriptionCatalogService::class);
    $current = $catalogService->catalogForProduct('broker');

    $res = $catalogService->storeForProduct('broker', [
        'base_price' => $current->base_price,
        'included_branches' => $current->included_branches,
        'users_per_branch' => $current->users_per_branch,
        'included_lr_credits' => $current->included_lr_credits,
        'included_trucks' => $current->included_trucks,
        'addon_branch_price' => $current->addon_branch_price,
        'addon_user_price' => $current->addon_user_price,
        'addon_truck_price' => $current->addon_truck_price,
        'is_active' => $current->is_active,
    ]);

    expect($res)->toBeNull();
});

test('product api retrieves active catalog and tax settings', function () {
    $catalogService = app(SubscriptionCatalogService::class);
    $payload = $catalogService->catalogPayload('broker');

    expect($payload)->toHaveKeys(['catalog', 'gst_rate_percent', 'settings'])
        ->and($payload['gst_rate_percent'])->toBe(18.0)
        ->and($payload['settings']['grace_period_days'])->toBe(app(SubscriptionSettingsService::class)->gracePeriodDays())
        ->and($payload['catalog']['product'])->toBe('broker')
        ->and((float) $payload['catalog']['base_price'])->toBe(20000.0);
});

test('promo code engine validates percentage and absolute discounts', function () {
    $catalogService = app(SubscriptionCatalogService::class);

    PromoCode::create([
        'code' => 'PERCENT25',
        'discount_type' => 'percentage',
        'discount_value' => 25,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    PromoCode::create([
        'code' => 'FLAT3000',
        'discount_type' => 'absolute',
        'discount_value' => 3000,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $pCheck = $catalogService->validatePromo('broker', 'percent25'); // Lowercase test
    expect($pCheck['valid'])->toBeTrue()
        ->and($pCheck['promo']['discount_type'])->toBe('percentage')
        ->and($pCheck['promo']['discount_value'])->toBe(25.0);

    $aCheck = $catalogService->validatePromo('broker', 'FLAT3000');
    expect($aCheck['valid'])->toBeTrue()
        ->and($aCheck['promo']['discount_type'])->toBe('absolute')
        ->and($aCheck['promo']['discount_value'])->toBe(3000.0);
});

test('promo code engine rejects future-dated or expired promo codes', function () {
    $catalogService = app(SubscriptionCatalogService::class);

    // Future coupon
    PromoCode::create([
        'code' => 'FUTURECOUPON',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->addDays(5)->toDateString(),
        'is_active' => true,
    ]);

    // Expired coupon
    PromoCode::create([
        'code' => 'EXPIREDCOUPON',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
        'is_active' => true,
    ]);

    $future = $catalogService->validatePromo('broker', 'FUTURECOUPON');
    expect($future['valid'])->toBeFalse()
        ->and($future['message'])->toBe('Promo code is not yet active.');

    $expired = $catalogService->validatePromo('broker', 'EXPIREDCOUPON');
    expect($expired['valid'])->toBeFalse()
        ->and($expired['message'])->toBe('Promo code has expired.');
});

test('promo code engine enforces max usage limits and increments atomically', function () {
    $catalogService = app(SubscriptionCatalogService::class);

    $promo = PromoCode::create([
        'code' => 'LIMIT2',
        'discount_type' => 'percentage',
        'discount_value' => 50,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDay()->toDateString(),
        'max_uses' => 2,
        'uses_count' => 1,
        'is_active' => true,
    ]);

    // First use allowed (uses_count = 1, max_uses = 2)
    expect($catalogService->validatePromo('broker', 'LIMIT2')['valid'])->toBeTrue();

    // Increment usage
    $catalogService->incrementPromoUse('LIMIT2');
    expect($promo->fresh()->uses_count)->toBe(2);

    // Limit reached
    $limitCheck = $catalogService->validatePromo('broker', 'LIMIT2');
    expect($limitCheck['valid'])->toBeFalse()
        ->and($limitCheck['message'])->toBe('Promo code usage limit reached.');
});

test('admin can extend trial date and amount via product api forward', function () {
    Http::fake([
        '*/api/admin/subscriptions/*/extend-trial' => Http::response(['success' => true, 'trial_ends_on' => '2026-12-31'], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 10]), [
            'trial_ends_on' => now()->addDays(30)->toDateString(),
            'amount' => 500.00,
            'reference' => 'EXTEND-REF-01',
            'notes' => 'Extended trial by 30 days',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Trial extended and charge recorded.');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/admin/subscriptions/10/extend-trial')
        && $request['amount'] == 500.00);
});

test('extending trial rejects past date', function () {
    Http::fake();
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 10]), [
            'trial_ends_on' => now()->subDays(5)->toDateString(),
            'amount' => 0.00,
        ])
        ->assertSessionHasErrors(['trial_ends_on']);

    Http::assertNothingSent();
});

test('admin can modify grace period and gst settings', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $settingsService = app(SubscriptionSettingsService::class);

    $this->actingAs($admin)
        ->patch(route('subscriptions.settings.update'), [
            'grace_period_days' => 14,
            'gst_rate_percent' => 18.0,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Subscription settings updated.');

    $current = $settingsService->currentSettings();
    expect((int) $current['grace_period_days'])->toBe(14);
});
