<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleWebController extends Controller
{
    public function index(): Response
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
            ]);

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'permissions' => Permission::query()->orderBy('name')->pluck('name'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless(auth()->user()?->can('roles.manage'), 403);

        if ($role->name === 'Super Admin') {
            return back()->with('error', 'Super Admin permissions cannot be modified.');
        }

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions']);

        activity('role')->performedOn($role)->causedBy($request->user())->log('Role permissions updated');

        return back()->with('success', 'Role permissions updated.');
    }
}
