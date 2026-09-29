<?php

use App\Models\PromoCode;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('deleting a promo code soft deletes the record', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $promo = PromoCode::create([
        'code' => 'SOFTDEL10',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/promo-codes/{$promo->id}")
        ->assertOk();

    expect(PromoCode::query()->find($promo->id))->toBeNull()
        ->and(PromoCode::withTrashed()->find($promo->id))->not->toBeNull()
        ->and(PromoCode::withTrashed()->find($promo->id)?->trashed())->toBeTrue();
});

test('hard delete is not allowed on promo codes', function () {
    $promo = PromoCode::create([
        'code' => 'NODELETE',
        'discount_type' => 'percentage',
        'discount_value' => 5,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    expect(fn () => $promo->forceDelete())
        ->toThrow(LogicException::class, 'Hard deletes are not allowed');
});
