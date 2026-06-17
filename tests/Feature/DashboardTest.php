<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);

    $user = User::query()->where('email', 'admin@truckbill.in')->first();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
