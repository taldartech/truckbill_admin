<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'subscriptions.view',
        'subscriptions.pricing',
        'subscriptions.settings',
        'subscriptions.promo_codes',
        'subscriptions.companies',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role !== null) {
                $role->givePermissionTo(self::PERMISSIONS);
            }
        }
    }

    public function down(): void
    {
        foreach (['Super Admin', 'Admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->first();

            if ($role !== null) {
                $role->revokePermissionTo(self::PERMISSIONS);
            }
        }

        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
    }
};
