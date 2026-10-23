<?php

use App\Models\SubscriptionPlanCatalog;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionQuoteService;
use App\Services\SubscriptionSettingsService;
use App\Support\Money;

test('money helper rounds amounts to 2 decimal places properly', function () {
    expect(Money::round(10.555))->toBe(10.56)
        ->and(Money::round(10.554))->toBe(10.55)
        ->and(Money::round(0))->toBe(0.0)
        ->and(Money::round('1234.567'))->toBe(1234.57)
        ->and(Money::round(-50.256))->toBe(-50.26);
});

test('money helper formats rupees with 2 decimal places as string', function () {
    expect(Money::format(25164.24))->toBe('25164.24')
        ->and(Money::format(100))->toBe('100.00')
        ->and(Money::format(0))->toBe('0.00')
        ->and(Money::format('9999.9'))->toBe('9999.90');
});

test('money helper displays formatted rupees with currency symbol and commas', function () {
    expect(Money::display(25164.24))->toBe('₹25,164.24')
        ->and(Money::display(1000000))->toBe('₹1,000,000.00')
        ->and(Money::display(0))->toBe('₹0.00');
});

test('money helper converts rupees to paise and vice versa with precision', function () {
    // 25164.24 rupees = 2516424 paise
    expect(Money::toPaise(25164.24))->toBe(2516424)
        ->and(Money::toPaise(0.01))->toBe(1)
        ->and(Money::toPaise(0))->toBe(0)
        ->and(Money::toPaise('500.50'))->toBe(50050);

    // 2516424 paise = 25164.24 rupees
    expect(Money::fromPaise(2516424))->toBe(25164.24)
        ->and(Money::fromPaise(1))->toBe(0.01)
        ->and(Money::fromPaise(0))->toBe(0.0);
});

test('subscription quote service calculates taxes and add-ons correctly with edge cases', function () {
    $catalogService = Mockery::mock(SubscriptionCatalogService::class);
    $settingsService = Mockery::mock(SubscriptionSettingsService::class);

    $catalogModel = new SubscriptionPlanCatalog([
        'product' => 'broker',
        'base_price' => 20000.00,
        'included_branches' => 2,
        'users_per_branch' => 3,
        'included_lr_credits' => 3600,
        'included_trucks' => 0,
        'addon_branch_price' => 6500.00,
        'addon_user_price' => 500.00,
        'addon_truck_price' => 0,
    ]);

    $catalogService->shouldReceive('catalogForProduct')
        ->with('broker')
        ->andReturn($catalogModel);

    $settingsService->shouldReceive('currentSettings')
        ->andReturn(['gst_rate_percent' => 18.0]);

    $quoteService = new SubscriptionQuoteService($catalogService, $settingsService);

    // Base quote (no add-ons)
    $baseQuote = $quoteService->estimate(['product' => 'broker']);
    expect($baseQuote['subtotal'])->toBe(20000.0)
        ->and($baseQuote['gst_amount'])->toBe(3600.0) // 18% of 20000
        ->and($baseQuote['total_amount'])->toBe(23600.0);

    // Quote with branches and users
    $heavyQuote = $quoteService->estimate([
        'product' => 'broker',
        'addon_branches' => 3, // 3 * 6500 = 19500
        'addon_users' => 10,   // 10 * 500 = 5000
    ]);
    // Subtotal = 20000 + 19500 + 5000 = 44500
    // GST = 44500 * 0.18 = 8010
    // Total = 52510
    expect($heavyQuote['subtotal'])->toBe(44500.0)
        ->and($heavyQuote['gst_amount'])->toBe(8010.0)
        ->and($heavyQuote['total_amount'])->toBe(52510.0);
});
