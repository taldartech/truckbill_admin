<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('settings redirects to security for all users', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $partner = User::query()->where('email', 'sales.partner@truckbill.in')->first();

    $this->actingAs($admin)
        ->get('/settings')
        ->assertRedirect(route('security.edit'));

    $this->actingAs($partner)
        ->get('/settings')
        ->assertRedirect(route('security.edit'));
});

test('legacy profile settings routes redirect to security', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertRedirect('/settings/security');
});

test('profile update route is no longer available', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'Changed Name',
            'email' => 'changed@example.com',
        ])
        ->assertRedirect('/settings/security');

    expect($user->fresh()->name)->not->toBe('Changed Name');
});

test('profile delete route is no longer available', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ])
        ->assertRedirect('/settings/security');

    expect($user->fresh())->not->toBeNull();
});
