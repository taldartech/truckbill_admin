<?php

use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('offline payment forwards a valid coupon to the product api', function () {
    Http::fake([
        '*/api/admin/subscriptions/*/offline-payment' => Http::response([
            'payment' => ['id' => 1, 'type' => 'renewal', 'promo_code' => 'SAVE10'],
            'subscription' => ['status' => 'active'],
        ], 200),
    ]);

    PromoCode::create([
        'code' => 'SAVE10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->from(route('subscriptions.companies.show', ['product' => 'broker', 'id' => 1]))
        ->post(route('subscriptions.companies.offline-payment', ['product' => 'broker', 'id' => 1]), [
            'purchase_type' => 'renewal',
            'promo_code' => 'save10',
            'reference' => 'UPI-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/api/admin/subscriptions/1/offline-payment')
        && $request['promo_code'] === 'save10');
});

test('offline payment rejects an invalid coupon without calling the product api', function () {
    Http::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->from(route('subscriptions.companies.show', ['product' => 'broker', 'id' => 1]))
        ->post(route('subscriptions.companies.offline-payment', ['product' => 'broker', 'id' => 1]), [
            'purchase_type' => 'renewal',
            'promo_code' => 'NOPE',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['promo_code' => 'Invalid promo code.']);

    Http::assertNothingSent();
});

test('offline payment rejects a coupon for another product', function () {
    Http::fake();

    PromoCode::create([
        'code' => 'FLEETONLY',
        'discount_type' => 'absolute',
        'discount_value' => 500,
        'applicable_products' => ['ownfleet'],
        'start_date' => now()->subDay()->toDateString(),
        'is_active' => true,
    ]);

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.offline-payment', ['product' => 'broker', 'id' => 1]), [
            'purchase_type' => 'renewal',
            'promo_code' => 'FLEETONLY',
        ])
        ->assertSessionHasErrors(['promo_code' => 'Promo code is not valid for this product.']);

    Http::assertNothingSent();
});

test('quote preview returns 422 for an invalid coupon', function () {
    Http::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->postJson(route('subscriptions.companies.quote', ['product' => 'broker', 'id' => 1]), [
            'purchase_type' => 'renewal',
            'promo_code' => 'NOPE',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Invalid promo code.');

    Http::assertNothingSent();
});
