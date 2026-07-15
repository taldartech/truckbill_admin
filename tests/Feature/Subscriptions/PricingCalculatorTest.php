<?php

use App\Models\User;
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

test('any authenticated user can access the pricing calculator', function () {
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $this->actingAs($partner)
        ->get(route('pricing-calculator'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Subscriptions/Calculator')
            ->has('catalog')
            ->has('gst_rate_percent')
            ->has('products'));
});

test('pricing calculator quote returns annual estimate with gst', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    $this->actingAs($exec)
        ->postJson(route('pricing-calculator.quote'), [
            'product' => 'broker',
            'addon_branches' => 1,
            'addon_users' => 2,
            'addon_trucks' => 0,
            'addon_lr_credits' => 100,
        ])
        ->assertOk()
        ->assertJsonPath('product', 'broker')
        ->assertJsonPath('subtotal', 27500)
        ->assertJsonPath('gst_rate_percent', 18)
        ->assertJsonPath('gst_amount', 4950)
        ->assertJsonPath('total_amount', 32450);
});

test('guests cannot access the pricing calculator', function () {
    $this->get(route('pricing-calculator'))->assertRedirect(route('login'));
});
