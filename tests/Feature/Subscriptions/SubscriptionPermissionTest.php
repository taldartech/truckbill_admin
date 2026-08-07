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

test('user without companies permission cannot extend trial', function () {
    $user = User::query()->where('email', 'sales@truckbill.in')->first();
    $user->givePermissionTo('subscriptions.view');

    $this->actingAs($user)
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 1]), [
            'trial_ends_on' => now()->addDays(14)->toDateString(),
            'amount' => 500,
        ])
        ->assertForbidden();
});

test('companies manager can extend trial via product api', function () {
    Illuminate\Support\Facades\Http::fake([
        '*/api/admin/subscriptions/*/extend-trial' => Illuminate\Support\Facades\Http::response([
            'payment' => ['id' => 1, 'type' => 'trial_extension'],
            'subscription' => ['status' => 'trial'],
        ], 200),
    ]);

    $user = User::query()->where('email', 'sales@truckbill.in')->first();
    $user->givePermissionTo(['subscriptions.view', 'subscriptions.companies']);

    $this->actingAs($user)
        ->from(route('subscriptions.companies.show', ['product' => 'broker', 'id' => 1]))
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 1]), [
            'trial_ends_on' => now()->addDays(14)->toDateString(),
            'amount' => 500,
            'reference' => 'REF-1',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    Illuminate\Support\Facades\Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/admin/subscriptions/1/extend-trial')
            && $request['amount'] == 500
            && isset($request['trial_ends_on'])
            && isset($request['admin_user_id']);
    });
});

test('extend trial requires trial end and amount', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($user)
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 1]), [])
        ->assertSessionHasErrors(['trial_ends_on', 'amount']);
});
