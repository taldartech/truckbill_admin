<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
    ]);
    config(['crm.website_api_key' => 'test-website-key']);
});

test('public leads api requires api key', function () {
    $this->postJson('/api/v1/public/leads', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'source' => 'website_demo',
    ])->assertUnauthorized();
});

test('public leads api creates lead', function () {
    $this->postJson('/api/v1/public/leads', [
        'name' => 'John Doe',
        'company_name' => 'Acme Transport',
        'mobile' => '9876543210',
        'email' => 'john@example.com',
        'product_interested_in' => 'broker',
        'source' => 'website_demo',
        'remarks' => 'Interested in demo',
        'utm_source' => 'google',
    ], ['X-Api-Key' => 'test-website-key'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    expect(Lead::query()->where('email', 'john@example.com')->exists())->toBeTrue();
});

test('authenticated user can view dashboard', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('sales executive only sees assigned leads', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    Lead::create([
        'name' => 'Assigned Lead',
        'email' => 'assigned@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $exec->id,
    ]);

    Lead::create([
        'name' => 'Other Lead',
        'email' => 'other@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => User::query()->where('email', 'sales.manager@truckbill.in')->value('id'),
    ]);

    $this->actingAs($exec)
        ->getJson('/api/v1/admin/leads')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
