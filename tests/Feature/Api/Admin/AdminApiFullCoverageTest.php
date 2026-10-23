<?php

use App\Models\Lead;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('admin api dashboard returns crm stats json', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/dashboards/crm')
        ->assertOk()
        ->assertJsonStructure(['total', 'new', 'contacted', 'qualified', 'trial', 'converted', 'lost', 'follow_up']);
});

test('admin api leads index supports search and filters', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    Lead::create(['name' => 'API Alpha', 'email' => 'alpha@test.com', 'source' => 'manual', 'status' => 'new', 'product' => 'broker']);
    Lead::create(['name' => 'API Beta', 'email' => 'beta@test.com', 'source' => 'manual', 'status' => 'qualified', 'product' => 'ownfleet']);

    $this->getJson('/api/v1/admin/leads?status=new')
        ->assertOk()
        ->assertJsonFragment(['name' => 'API Alpha']);

    $this->getJson('/api/v1/admin/leads?product=ownfleet')
        ->assertOk()
        ->assertJsonFragment(['name' => 'API Beta']);

    $this->getJson('/api/v1/admin/leads?search=Alpha')
        ->assertOk()
        ->assertJsonFragment(['name' => 'API Alpha']);
});

test('admin api can store, show and update a lead', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    // 1. Store
    $storeRes = $this->postJson('/api/v1/admin/leads', [
        'name' => 'New API Lead',
        'email' => 'api.lead@example.com',
        'company_name' => 'API Transport',
        'mobile' => '9877700000',
        'product' => 'broker',
        'source' => 'manual',
    ])->assertStatus(201);

    $leadId = $storeRes->json('id');
    $lead = Lead::findOrFail($leadId);

    // 2. Show
    $this->getJson("/api/v1/admin/leads/{$leadId}")
        ->assertOk()
        ->assertJsonPath('name', 'New API Lead');

    // 3. Update details and status
    $this->putJson("/api/v1/admin/leads/{$leadId}", [
        'name' => 'Updated API Lead',
        'status' => 'lost',
        'remarks' => 'Price too high for current budget',
    ])->assertOk()
        ->assertJsonPath('name', 'Updated API Lead')
        ->assertJsonPath('status', 'lost');
});

test('admin api can add notes and view activities on a lead', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $lead = Lead::create(['name' => 'Note Lead', 'email' => 'note@api.com', 'source' => 'manual', 'status' => 'new']);

    $this->postJson("/api/v1/admin/leads/{$lead->id}/notes", [
        'body' => 'Important follow up note from API client',
    ])->assertStatus(201)
        ->assertJsonPath('body', 'Important follow up note from API client');

    $this->getJson("/api/v1/admin/leads/{$lead->id}/activities")
        ->assertOk();
});

test('admin api invitation controller sends invitation and returns url', function () {
    Notification::fake();
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $lead = Lead::create(['name' => 'Invite Lead', 'email' => 'invite@api.com', 'source' => 'manual', 'status' => 'qualified']);

    $this->postJson("/api/v1/admin/leads/{$lead->id}/invite", [
        'product' => 'broker',
    ])->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['url', 'invitation_id']);
});

test('admin api user controller lists and creates users', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonStructure(['data']);

    $this->postJson('/api/v1/admin/users', [
        'name' => 'New API Sales Exec',
        'email' => 'api.sales@truckbill.in',
        'password' => 'SecurePass2026!',
        'role' => 'Sales Executive',
        'manager_id' => $admin->id,
    ])->assertStatus(201)
        ->assertJsonPath('name', 'New API Sales Exec');
});

test('admin api subscription catalog lists and updates pricing', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/subscription-catalog')
        ->assertOk()
        ->assertJsonStructure(['catalog', 'products']);

    // Update with new pricing
    $this->patchJson('/api/v1/admin/subscription-catalog/broker', [
        'base_price' => 25000.00,
        'included_branches' => 2,
        'users_per_branch' => 3,
        'included_lr_credits' => 4000,
        'included_trucks' => 0,
        'addon_branch_price' => 6500.00,
        'addon_user_price' => 500.00,
        'addon_truck_price' => 0,
    ])->assertOk()
        ->assertJsonPath('catalog.base_price', '25000.00');

    // Update with identical pricing returns no changes message
    $this->patchJson('/api/v1/admin/subscription-catalog/broker', [
        'base_price' => 25000.00,
        'included_branches' => 2,
        'users_per_branch' => 3,
        'included_lr_credits' => 4000,
        'included_trucks' => 0,
        'addon_branch_price' => 6500.00,
        'addon_user_price' => 500.00,
        'addon_truck_price' => 0,
    ])->assertOk()
        ->assertJsonPath('message', 'No changes to save.');
});

test('admin api subscription settings show and update', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/subscription-settings')
        ->assertOk()
        ->assertJsonStructure(['settings', 'meta']);

    $this->patchJson('/api/v1/admin/subscription-settings', [
        'grace_period_days' => 10,
        'gst_rate_percent' => 18.0,
    ])->assertOk()
        ->assertJsonPath('settings.grace_period_days', 10);
});

test('admin api promo code controller full crud lifecycle', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    Sanctum::actingAs($admin);

    // 1. Index
    $this->getJson('/api/v1/admin/promo-codes')
        ->assertOk()
        ->assertJsonStructure(['promo_codes', 'products', 'discount_types']);

    // 2. Store
    $storeRes = $this->postJson('/api/v1/admin/promo-codes', [
        'code' => 'APICRUD20',
        'discount_type' => 'percentage',
        'discount_value' => 20,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
    ])->assertStatus(201);

    $promoId = $storeRes->json('promo_code.id');

    // 3. Update
    $this->patchJson("/api/v1/admin/promo-codes/{$promoId}", [
        'code' => 'APICRUD25',
        'discount_type' => 'percentage',
        'discount_value' => 25,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
    ])->assertOk()
        ->assertJsonPath('promo_code.code', 'APICRUD25')
        ->assertJsonPath('promo_code.discount_value', '25.00');

    // 4. Destroy
    $this->deleteJson("/api/v1/admin/promo-codes/{$promoId}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PromoCode::query()->where('id', $promoId)->exists())->toBeFalse()
        ->and(PromoCode::withTrashed()->where('id', $promoId)->exists())->toBeTrue();
});
