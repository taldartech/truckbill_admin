<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('super admin can access subscription pricing', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->get(route('subscriptions.pricing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Subscriptions/Pricing'));
});

test('user without subscription permissions cannot access subscription pages', function () {
    $salesExec = User::query()->where('email', 'sales@truckbill.in')->first();

    $this->actingAs($salesExec)
        ->get(route('subscriptions.pricing'))
        ->assertForbidden();
});

test('view-only user cannot update pricing', function () {
    $user = User::query()->where('email', 'sales@truckbill.in')->first();
    $user->givePermissionTo('subscriptions.view');

    $this->actingAs($user)
        ->patch(route('subscriptions.pricing.update', 'broker'), [
            'base_price' => 1000,
            'included_branches' => 2,
            'users_per_branch' => 3,
            'included_lr_credits' => 3600,
            'included_trucks' => 0,
            'addon_branch_price' => 100,
            'addon_user_price' => 50,
            'addon_truck_price' => 0,
            'is_active' => true,
        ])
        ->assertForbidden();
});

test('pricing manager can update pricing but not promo codes', function () {
    $user = User::query()->where('email', 'sales@truckbill.in')->first();
    $user->givePermissionTo(['subscriptions.view', 'subscriptions.pricing']);

    $this->actingAs($user)
        ->get(route('subscriptions.promo-codes.create'))
        ->assertForbidden();
});
