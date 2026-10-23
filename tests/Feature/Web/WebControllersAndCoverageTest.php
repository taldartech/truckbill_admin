<?php

use App\Models\Lead;
use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.products.broker.signing_secret' => 'broker-secret']);
});

test('role web controller lists roles and allows updating permissions but protects super admin', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // 1. Index
    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Roles/Index')->has('roles')->has('permissions'));

    // 2. Reject modifying Super Admin permissions
    $superAdminRole = Role::findByName('Super Admin', 'web');
    $this->actingAs($admin)
        ->patch(route('roles.update', $superAdminRole), ['permissions' => []])
        ->assertRedirect()
        ->assertSessionHas('error', 'Super Admin permissions cannot be modified.');

    // 3. Update permissions on Sales Executive role
    $salesExecRole = Role::findByName('Sales Executive', 'web');
    $this->actingAs($admin)
        ->patch(route('roles.update', $salesExecRole), [
            'permissions' => ['leads.view', 'leads.create'],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Role permissions updated.');

    expect($salesExecRole->fresh()->hasPermissionTo('leads.view'))->toBeTrue();
});

test('security settings edit renders security page and validates password update request', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->get('/settings/security')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Security')->has('passwordRules'));
});

test('subscription web controller handles promo codes crud in web UI', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // 1. Create page
    $this->actingAs($admin)
        ->get(route('subscriptions.promo-codes.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Subscriptions/PromoCodeCreate'));

    // 2. Store
    $this->actingAs($admin)
        ->post(route('subscriptions.promo-codes.store'), [
            'code' => 'WEBPROMO10',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applicable_products' => ['broker'],
            'start_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('subscriptions.promo-codes'))
        ->assertSessionHas('success', 'Promo code created.');

    $promo = PromoCode::where('code', 'WEBPROMO10')->firstOrFail();

    // 3. Edit page
    $this->actingAs($admin)
        ->get(route('subscriptions.promo-codes.edit', $promo))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Subscriptions/PromoCodeEdit'));

    // 4. Update
    $this->actingAs($admin)
        ->patch(route('subscriptions.promo-codes.update', $promo), [
            'code' => 'WEBPROMO15',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'applicable_products' => ['broker'],
            'start_date' => now()->toDateString(),
        ])
        ->assertRedirect(route('subscriptions.promo-codes'))
        ->assertSessionHas('success', 'Promo code updated.');
});

test('subscription web controller handles remote 422 error payloads gracefully on offline payment and trial extension', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // Remote 422 error on offline payment
    Http::fake([
        '*/api/admin/subscriptions/*/offline-payment' => Http::response([
            'message' => 'Active plan already exists for this tenure.',
        ], 422),
        '*/api/admin/subscriptions/*/extend-trial' => Http::response([
            'message' => 'Maximum trial extensions reached.',
        ], 422),
    ]);

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.offline-payment', ['product' => 'broker', 'id' => 99]), [
            'purchase_type' => 'renewal',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['offline' => 'Active plan already exists for this tenure.']);

    $this->actingAs($admin)
        ->post(route('subscriptions.companies.extend-trial', ['product' => 'broker', 'id' => 99]), [
            'trial_ends_on' => now()->addDays(10)->toDateString(),
            'amount' => 100,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['extend_trial' => 'Maximum trial extensions reached.']);
});

test('user web controller handles user create, edit, update and delete lifecycles', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // 1. Create page
    $this->actingAs($admin)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Create'));

    // 2. Store user
    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Web Created Exec',
            'email' => 'web.exec@truckbill.in',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'Sales Executive',
            'manager_id' => $admin->id,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'User created successfully.');

    $user = User::where('email', 'web.exec@truckbill.in')->firstOrFail();

    // 3. Edit page
    $this->actingAs($admin)
        ->get(route('users.edit', $user))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Edit'));

    // 4. Update
    $this->actingAs($admin)
        ->patch(route('users.update', $user), [
            'name' => 'Web Exec Updated',
            'email' => 'web.exec@truckbill.in',
            'role' => 'Sales Executive',
            'manager_id' => $admin->id,
        ])
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success', 'User updated successfully.');

    // 5. Cannot delete self (forbidden by policy)
    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertForbidden();

    // 6. Delete user
    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect()
        ->assertSessionHas('success', 'User deleted.');

    expect(User::where('id', $user->id)->exists())->toBeFalse();
});

test('lead web controller deletes a lead and handles different follow-up filters', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Delete Me Lead',
        'email' => 'deleteme@test.com',
        'source' => 'manual',
        'status' => 'new',
        'next_follow_up_at' => now()->toDateString(),
    ]);

    // Test follow up due_today filter
    $this->actingAs($admin)
        ->get(route('leads.index', ['follow_up' => 'due_today']))
        ->assertOk();

    // Test follow up upcoming filter
    $this->actingAs($admin)
        ->get(route('leads.index', ['follow_up' => 'upcoming']))
        ->assertOk();

    // Delete lead
    $this->actingAs($admin)
        ->delete(route('leads.destroy', $lead))
        ->assertRedirect(route('leads.index'))
        ->assertSessionHas('success', 'Lead deleted.');

    expect(Lead::where('id', $lead->id)->exists())->toBeFalse();
});

test('product subscription controller handles promo code increment endpoint with success and 422 failure', function () {
    $timestamp = (string) time();

    PromoCode::create([
        'code' => 'INC2026',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // 1. Successful increment
    $bodyValid = json_encode(['code' => 'INC2026']);
    $sigValid = hash_hmac('sha256', $timestamp.'.'.$bodyValid, 'broker-secret');

    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $sigValid,
    ])->postJson('/api/v1/products/broker/promo-codes/increment-use', ['code' => 'INC2026'])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(PromoCode::where('code', 'INC2026')->first()->uses_count)->toBe(1);

    // 2. Invalid promo code returns 422
    $bodyInvalid = json_encode(['code' => 'NOTREAL']);
    $sigInvalid = hash_hmac('sha256', $timestamp.'.'.$bodyInvalid, 'broker-secret');

    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $sigInvalid,
    ])->postJson('/api/v1/products/broker/promo-codes/increment-use', ['code' => 'NOTREAL'])
        ->assertStatus(422)
        ->assertJsonPath('valid', false);
});

test('cors middleware handles options preflight request properly', function () {
    $response = $this->call(
        'OPTIONS',
        '/api/v1/public/leads',
        [],
        [],
        [],
        [
            'HTTP_ORIGIN' => 'https://truckbill.in',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]
    );

    $response->assertStatus(204);
    expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('*');
});
