<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed([RoleAndPermissionSeeder::class, AdminUserSeeder::class]);
});

test('support user can view lead but cannot update it', function () {
    $support = User::factory()->create();
    $support->assignRole('Support');

    $lead = Lead::create([
        'name' => 'Support Lead',
        'email' => 'support-lead@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $this->actingAs($support)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', false)
            ->where('canAssign', false));

    $this->actingAs($support)
        ->patch(route('leads.update', $lead), ['status' => 'contacted'])
        ->assertForbidden();
});

test('sales executive cannot assign leads', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();
    $manager = User::query()->where('email', 'sales.manager@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Assignable Lead',
        'email' => 'assign@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $exec->id,
    ]);

    $this->actingAs($exec)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canUpdate', true)
            ->where('canAssign', false));

    $this->actingAs($exec)
        ->patch(route('leads.update', $lead), ['assigned_to' => $manager->id])
        ->assertForbidden();
});

test('sales manager can assign leads to team members', function () {
    $manager = User::query()->where('email', 'sales.manager@truckbill.in')->first();
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Team Lead',
        'email' => 'team-lead@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->patch(route('leads.update', $lead), ['assigned_to' => $exec->id])
        ->assertRedirect();

    expect($lead->fresh()->assigned_to)->toBe($exec->id);
});

test('user without leads view cannot access leads index', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('users.manage');

    $this->actingAs($user)
        ->get(route('leads.index'))
        ->assertForbidden();
});

test('user without leads assign cannot set assignee on create', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();
    $manager = User::query()->where('email', 'sales.manager@truckbill.in')->first();

    $this->actingAs($exec)
        ->post(route('leads.store'), [
            'name' => 'New Lead',
            'email' => 'new-lead@example.com',
            'assigned_to' => $manager->id,
        ])
        ->assertForbidden();
});
