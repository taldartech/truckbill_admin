<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'leads.view',
            'leads.create',
            'leads.update',
            'leads.delete',
            'leads.assign',
            'leads.invite',
            'users.manage',
            'roles.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $rolePermissions = [
            'Super Admin' => $permissions,
            'Admin' => $permissions,
            'Sales Manager' => ['leads.view', 'leads.create', 'leads.update', 'leads.assign', 'leads.invite'],
            'Sales Executive' => ['leads.view', 'leads.create', 'leads.update', 'leads.invite'],
            'Support' => ['leads.view'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($perms);
        }
    }
}
