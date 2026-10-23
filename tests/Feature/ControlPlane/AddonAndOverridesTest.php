<?php

use App\Models\User;
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
});

test('paid add-on quote calculates price for additional branches, users and trucks', function () {
    $quoteService = app(SubscriptionQuoteService::class);

    // OwnFleet add-ons: 2 extra branches (2 * 6500), 5 extra users (5 * 500), 20 extra trucks (20 * 365)
    // Base plan: 20000
    // Branches: 13000
    // Users: 2500
    // Trucks: 7300
    // Subtotal: 42800
    // GST 18%: 7704 => Total: 50504
    $quote = $quoteService->estimate([
        'product' => 'ownfleet',
        'addon_branches' => 2,
        'addon_users' => 5,
        'addon_trucks' => 20,
    ]);

    expect($quote['subtotal'])->toBe(42800.0)
        ->and($quote['gst_amount'])->toBe(7704.0)
        ->and($quote['total_amount'])->toBe(50504.0)
        ->and($quote['quantities']['addon_branches'])->toBe(2)
        ->and($quote['quantities']['addon_users'])->toBe(5)
        ->and($quote['quantities']['addon_trucks'])->toBe(20);
});

test('admin can record offline payment specifically for paid add-ons', function () {
    Http::fake([
        '*/api/admin/subscriptions/*/offline-payment' => Http::response([
            'payment' => ['id' => 88, 'type' => 'addon', 'amount' => 15340],
            'subscription' => ['addon_branches' => 2],
        ], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.offline-payment', ['product' => 'broker', 'id' => 5]), [
            'purchase_type' => 'addon',
            'addon_branches' => 2,
            'addon_users' => 0,
            'reference' => 'ADDON-CHQ-100',
            'notes' => 'Added 2 branches',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Offline payment recorded and plan updated.');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/admin/subscriptions/5/offline-payment')
        && $request['purchase_type'] === 'addon'
        && $request['addon_branches'] == 2);
});

test('admin can remove or reduce paid add-ons down to zero', function () {
    Http::fake([
        '*/api/admin/subscriptions/*' => Http::response(['status' => 'updated'], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // Reduce add-on branches to 0
    $this->actingAs($admin)
        ->patch(route('subscriptions.companies.update', ['product' => 'broker', 'id' => 5]), [
            'addon_branches' => 0,
            'addon_users' => 0,
            'addon_trucks' => 0,
            'notes' => 'Customer removed all optional add-on branches and users',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Subscription updated.');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && $request['addon_branches'] === 0
        && $request['addon_users'] === 0);
});

test('add-on updates reject negative quantities', function () {
    Http::fake();
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->patch(route('subscriptions.companies.update', ['product' => 'broker', 'id' => 5]), [
            'addon_branches' => -1,
        ])
        ->assertSessionHasErrors(['addon_branches']);

    Http::assertNothingSent();
});

test('admin can set customer-level overrides for included limits, lr credits and status', function () {
    Http::fake([
        '*/api/admin/subscriptions/*' => Http::response(['status' => 'updated'], 200),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // Customer-level override: Give enterprise customer 10 included branches, 10 users per branch, 10000 LR credits
    $this->actingAs($admin)
        ->patch(route('subscriptions.companies.update', ['product' => 'broker', 'id' => 12]), [
            'status' => 'active',
            'included_branches' => 10,
            'users_per_branch' => 10,
            'included_lr_credits' => 10000,
            'lr_credits_remaining' => 8500,
            'notes' => 'Custom enterprise SLA override granted by management',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Subscription updated.');

    Http::assertSent(function ($request) {
        return $request['status'] === 'active'
            && $request['included_branches'] === 10
            && $request['users_per_branch'] === 10
            && $request['included_lr_credits'] === 10000
            && $request['lr_credits_remaining'] === 8500;
    });
});

test('updating customer-level subscription handles 422 no-change response gracefully', function () {
    Http::fake([
        '*/api/admin/subscriptions/*' => Http::response([
            'message' => 'Product API error: 422 No changes to save.',
        ], 422),
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->patch(route('subscriptions.companies.update', ['product' => 'broker', 'id' => 12]), [
            'notes' => 'Same notes',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'No changes to save.');
});
