<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('admin can access users index', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Index'));
});

test('admin can create crm user', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New Sales Rep',
            'email' => 'rep@truckbill.in',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
            'role' => 'Sales Executive',
        ])
        ->assertRedirect(route('users.index'));

    expect(User::query()->where('email', 'rep@truckbill.in')->exists())->toBeTrue();
});

test('admin can manually create lead via web', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('leads.store'), [
            'name' => 'Manual Lead',
            'email' => 'manual@example.com',
            'mobile' => '9876543210',
            'company_name' => 'Manual Co',
            'product' => 'broker',
            'status' => 'new',
            'source' => 'manual',
        ])
        ->assertRedirect();

    expect(Lead::query()->where('email', 'manual@example.com')->where('source', 'manual')->exists())->toBeTrue();
});

test('sales executive cannot access users management', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    $this->actingAs($exec)
        ->get(route('users.index'))
        ->assertForbidden();
});

test('admin can access roles page', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Roles/Index'));
});

test('admin can view sales partner profile on user edit page', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $partner->salesPartnerProfile()->create([
        'legal_name' => 'Partner Legal Name',
        'mobile' => '9876543210',
        'working_location' => 'Mumbai',
        'current_company_name' => 'Acme Logistics',
        'pan_number' => 'ABCDE1234F',
        'aadhar_number' => '123456789012',
        'bank_name' => 'HDFC Bank',
        'bank_account_number' => '1234567890',
        'ifsc_code' => 'HDFC0001234',
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('users.edit', $partner))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Users/Edit')
            ->where('partnerOnboardingStatus', 'onboarded')
            ->where('salesPartnerProfile.legal_name', 'Partner Legal Name')
            ->where('salesPartnerProfile.mobile', '9876543210')
            ->where('salesPartnerProfile.pan_number', 'ABCDE1234F'));
});

test('deleting a user soft deletes the record', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $user = User::factory()->create([
        'email' => 'soft-delete-user@truckbill.in',
    ]);
    $user->assignRole('Sales Executive');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect(User::query()->find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::withTrashed()->find($user->id)?->trashed())->toBeTrue();
});

test('soft-deleted user email can be reused', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $user = User::factory()->create([
        'email' => 'reuse-email@truckbill.in',
    ]);
    $user->assignRole('Sales Executive');
    $user->delete();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Reused Email User',
            'email' => 'reuse-email@truckbill.in',
            'password' => 'password123!',
            'password_confirmation' => 'password123!',
            'role' => 'Sales Executive',
        ])
        ->assertRedirect(route('users.index'));

    expect(User::query()->where('email', 'reuse-email@truckbill.in')->exists())->toBeTrue();
});
