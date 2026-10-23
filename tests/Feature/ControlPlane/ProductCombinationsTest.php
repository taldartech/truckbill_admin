<?php

use App\Enums\Product;
use App\Models\PromoCode;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionQuoteService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('combination 1: broker only product configuration and pricing', function () {
    $quoteService = app(SubscriptionQuoteService::class);
    $features = $quoteService->productFeatures(Product::Broker);

    expect($features['lr_credits'])->toBeTrue()
        ->and($features['trucks'])->toBeFalse();

    $quote = $quoteService->estimate([
        'product' => 'broker',
        'addon_branches' => 1,
        'addon_users' => 2,
        'addon_trucks' => 5, // Should be ignored because trucks is false for broker
        'addon_lr_credits' => 1000,
    ]);

    // Base plan (20000) + 1 branch (6500) + 2 users (1000) = 27500
    // GST 18% = 4950 => Total 32450
    expect($quote['product'])->toBe('broker')
        ->and($quote['subtotal'])->toBe(27500.0)
        ->and($quote['total_amount'])->toBe(32450.0)
        ->and($quote['quantities']['addon_trucks'])->toBe(0);

    // Single-product promo code for Broker
    PromoCode::create([
        'code' => 'BROKERONLY',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('broker', 'BROKERONLY')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('ownfleet', 'BROKERONLY')['valid'])->toBeFalse()
        ->and($catalogService->validatePromo('parchun', 'BROKERONLY')['valid'])->toBeFalse();
});

test('combination 2: ownfleet only product configuration and pricing', function () {
    $quoteService = app(SubscriptionQuoteService::class);
    $features = $quoteService->productFeatures(Product::OwnFleet);

    expect($features['trucks'])->toBeTrue()
        ->and($features['lr_credits'])->toBeFalse();

    $quote = $quoteService->estimate([
        'product' => 'ownfleet',
        'addon_branches' => 0,
        'addon_users' => 0,
        'addon_trucks' => 10, // 10 * 365 = 3650
        'addon_lr_credits' => 500, // Should be ignored
    ]);

    // Base plan (20000) + 10 trucks * 365 (3650) = 23650
    // GST 18% = 4257 => Total = 27907
    expect($quote['product'])->toBe('ownfleet')
        ->and($quote['subtotal'])->toBe(23650.0)
        ->and($quote['gst_amount'])->toBe(4257.0)
        ->and($quote['total_amount'])->toBe(27907.0)
        ->and($quote['quantities']['addon_lr_credits'])->toBe(0);

    // Single-product promo code for OwnFleet
    PromoCode::create([
        'code' => 'FLEETONLY',
        'discount_type' => 'absolute',
        'discount_value' => 2000,
        'applicable_products' => ['ownfleet'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('ownfleet', 'FLEETONLY')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('broker', 'FLEETONLY')['valid'])->toBeFalse()
        ->and($catalogService->validatePromo('parchun', 'FLEETONLY')['valid'])->toBeFalse();
});

test('combination 3: partload only product configuration and pricing', function () {
    $quoteService = app(SubscriptionQuoteService::class);
    $features = $quoteService->productFeatures(Product::Parchun);

    expect($features['trucks'])->toBeFalse()
        ->and($features['lr_credits'])->toBeFalse();

    $quote = $quoteService->estimate([
        'product' => 'parchun',
        'addon_branches' => 2, // 2 * 6500 = 13000
        'addon_users' => 4,    // 4 * 500 = 2000
        'addon_trucks' => 5,   // ignored
        'addon_lr_credits' => 100, // ignored
    ]);

    // Base (20000) + Branches (13000) + Users (2000) = 35000
    // GST 18% = 6300 => Total 41300
    expect($quote['product'])->toBe('parchun')
        ->and($quote['subtotal'])->toBe(35000.0)
        ->and($quote['total_amount'])->toBe(41300.0)
        ->and($quote['quantities']['addon_trucks'])->toBe(0)
        ->and($quote['quantities']['addon_lr_credits'])->toBe(0);

    PromoCode::create([
        'code' => 'PARCHUNONLY',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'applicable_products' => ['parchun'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('parchun', 'PARCHUNONLY')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('broker', 'PARCHUNONLY')['valid'])->toBeFalse()
        ->and($catalogService->validatePromo('ownfleet', 'PARCHUNONLY')['valid'])->toBeFalse();
});

test('combination 4: dual bundle broker and ownfleet', function () {
    // Dual product promo code
    $promo = PromoCode::create([
        'code' => 'DUALBROKERFLEET',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'applicable_products' => ['broker', 'ownfleet'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $catalogService = app(SubscriptionCatalogService::class);
    expect($promo->appliesToProduct('broker'))->toBeTrue()
        ->and($promo->appliesToProduct('ownfleet'))->toBeTrue()
        ->and($promo->appliesToProduct('parchun'))->toBeFalse();

    expect($catalogService->validatePromo('broker', 'DUALBROKERFLEET')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('ownfleet', 'DUALBROKERFLEET')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('parchun', 'DUALBROKERFLEET')['valid'])->toBeFalse();

    // Dual quote calculation
    $quoteService = app(SubscriptionQuoteService::class);
    $brokerQuote = $quoteService->estimate(['product' => 'broker', 'addon_branches' => 1]);
    $fleetQuote = $quoteService->estimate(['product' => 'ownfleet', 'addon_trucks' => 10]);

    $bundleSubtotal = $brokerQuote['subtotal'] + $fleetQuote['subtotal'];
    $bundleGst = $brokerQuote['gst_amount'] + $fleetQuote['gst_amount'];
    $bundleTotal = $brokerQuote['total_amount'] + $fleetQuote['total_amount'];

    expect($bundleSubtotal)->toBe(26500.0 + 23650.0) // 50150
        ->and($bundleTotal)->toBe(31270.0 + 27907.0); // 59177
});

test('combination 5: dual bundle broker and partload', function () {
    $promo = PromoCode::create([
        'code' => 'BROKERPARCHUN',
        'discount_type' => 'absolute',
        'discount_value' => 5000,
        'applicable_products' => ['broker', 'parchun'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    expect($promo->appliesToProduct('broker'))->toBeTrue()
        ->and($promo->appliesToProduct('parchun'))->toBeTrue()
        ->and($promo->appliesToProduct('ownfleet'))->toBeFalse();

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('broker', 'BROKERPARCHUN')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('parchun', 'BROKERPARCHUN')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('ownfleet', 'BROKERPARCHUN')['valid'])->toBeFalse();
});

test('combination 6: dual bundle ownfleet and partload', function () {
    $promo = PromoCode::create([
        'code' => 'FLEETPARCHUN',
        'discount_type' => 'percentage',
        'discount_value' => 25,
        'applicable_products' => ['ownfleet', 'parchun'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    expect($promo->appliesToProduct('ownfleet'))->toBeTrue()
        ->and($promo->appliesToProduct('parchun'))->toBeTrue()
        ->and($promo->appliesToProduct('broker'))->toBeFalse();

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('ownfleet', 'FLEETPARCHUN')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('parchun', 'FLEETPARCHUN')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('broker', 'FLEETPARCHUN')['valid'])->toBeFalse();
});

test('combination 7: tri-product unified suite broker, ownfleet and partload', function () {
    // Universal 3-product promo code
    $promo = PromoCode::create([
        'code' => 'UNIFIEDALL3',
        'discount_type' => 'percentage',
        'discount_value' => 30,
        'applicable_products' => ['broker', 'ownfleet', 'parchun'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $catalogService = app(SubscriptionCatalogService::class);
    expect($catalogService->validatePromo('broker', 'UNIFIEDALL3')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('ownfleet', 'UNIFIEDALL3')['valid'])->toBeTrue()
        ->and($catalogService->validatePromo('parchun', 'UNIFIEDALL3')['valid'])->toBeTrue();

    // Verify all 3 products have distinct catalogs
    $brokerCatalog = $catalogService->catalogForProduct('broker');
    $fleetCatalog = $catalogService->catalogForProduct('ownfleet');
    $parchunCatalog = $catalogService->catalogForProduct('parchun');

    expect($brokerCatalog)->not->toBeNull()
        ->and($fleetCatalog)->not->toBeNull()
        ->and($parchunCatalog)->not->toBeNull();

    expect($brokerCatalog->included_lr_credits)->toBe(3600)
        ->and($fleetCatalog->included_trucks)->toBe(50)
        ->and($parchunCatalog->included_trucks)->toBe(0);

    // Verify full suite quote calculation
    $quoteService = app(SubscriptionQuoteService::class);
    $bQuote = $quoteService->estimate(['product' => 'broker']);
    $fQuote = $quoteService->estimate(['product' => 'ownfleet']);
    $pQuote = $quoteService->estimate(['product' => 'parchun']);

    $totalBase = $bQuote['subtotal'] + $fQuote['subtotal'] + $pQuote['subtotal'];
    expect($totalBase)->toBe(60000.0); // 20k + 20k + 20k
});

test('all seven combinations can be managed through subscription catalog', function () {
    $catalogService = app(SubscriptionCatalogService::class);
    $rows = $catalogService->latestCatalogRows();

    // Verify distinct products present in catalog
    $products = $rows->pluck('product')->all();
    expect($products)->toContain('broker')
        ->and($products)->toContain('ownfleet')
        ->and($products)->toContain('parchun');

    expect(count(Product::cases()))->toBe(3);
    // Number of non-empty combinations of 3 elements = 2^3 - 1 = 7 combinations
    $combinations = [
        ['broker'],
        ['ownfleet'],
        ['parchun'],
        ['broker', 'ownfleet'],
        ['broker', 'parchun'],
        ['ownfleet', 'parchun'],
        ['broker', 'ownfleet', 'parchun'],
    ];

    expect(count($combinations))->toBe(7);

    // Verify each combination can be stored as a promo code target
    foreach ($combinations as $combo) {
        $code = 'TEST_'.implode('_', $combo);
        $promo = PromoCode::create([
            'code' => $code,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applicable_products' => $combo,
            'start_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        foreach ($combo as $prod) {
            expect($promo->appliesToProduct($prod))->toBeTrue();
        }
    }
});
