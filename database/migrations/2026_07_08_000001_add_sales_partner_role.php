<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'leads.view',
        'leads.create',
        'leads.update',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => 'Sales Partner', 'guard_name' => 'web']);
        $role->syncPermissions(self::PERMISSIONS);
    }

    public function down(): void
    {
        Role::query()->where('name', 'Sales Partner')->delete();
    }
};
