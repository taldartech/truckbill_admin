<?php

use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadCapturedNotification;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
    ]);
    config(['crm.website_api_key' => 'test-website-key']);
});

test('public leads api requires api key', function () {
    $this->postJson('/api/v1/public/leads', [
        'name' => 'Test',
        'email' => 'test@example.com',
        'source' => 'website_demo',
    ])->assertUnauthorized();
});

test('public leads api creates lead', function () {
    Notification::fake();

    $this->postJson('/api/v1/public/leads', [
        'name' => 'John Doe',
        'company_name' => 'Acme Transport',
        'mobile' => '9876543210',
        'email' => 'john@example.com',
        'product_interested_in' => 'broker',
        'source' => 'website_demo',
        'remarks' => 'Interested in demo',
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'demo',
        'landing_url' => 'https://truckbill.in/?utm_source=google',
        'referrer' => 'https://google.com/',
    ], ['X-Api-Key' => 'test-website-key'])
        ->assertCreated()
        ->assertJsonPath('success', true);

    $lead = Lead::query()->where('email', 'john@example.com')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->utm_source)->toBe('google')
        ->and($lead->utm_medium)->toBe('cpc')
        ->and($lead->utm_campaign)->toBe('demo')
        ->and($lead->landing_url)->toBe('https://truckbill.in/?utm_source=google')
        ->and($lead->referrer)->toBe('https://google.com/');

    $admins = User::role(['Super Admin', 'Admin'])->get();
    expect($admins)->not->toBeEmpty();

    Notification::assertSentTo($admins, LeadCapturedNotification::class);
    Notification::assertNotSentTo(
        User::role(['Sales Executive', 'Sales Partner'])->get(),
        LeadCapturedNotification::class,
    );
});

test('lead show includes acquisition source and medium', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Acquisition Lead',
        'email' => 'acquisition@example.com',
        'source' => 'website_demo',
        'status' => 'new',
        'utm_source' => 'facebook',
        'utm_medium' => 'paid',
        'utm_campaign' => 'spring',
        'landing_url' => 'https://truckbill.in/pricing',
        'referrer' => 'https://facebook.com/',
    ]);

    $this->actingAs($admin)
        ->get(route('leads.show', $lead))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leads/Show')
            ->where('lead.utm_source', 'facebook')
            ->where('lead.utm_medium', 'paid')
            ->where('lead.utm_campaign', 'spring')
            ->where('lead.landing_url', 'https://truckbill.in/pricing')
            ->where('lead.referrer', 'https://facebook.com/'));
});

test('manual lead create does not send captured notification', function () {
    Notification::fake();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $this->actingAs($admin)
        ->post(route('leads.store'), [
            'name' => 'Manual Lead',
            'email' => 'manual-notif@example.com',
            'mobile' => '9876543210',
            'company_name' => 'Manual Co',
            'product' => 'broker',
            'status' => 'new',
            'source' => 'manual',
        ])
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('authenticated user can view dashboard', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    Lead::create([
        'name' => 'Broker Lead',
        'email' => 'broker-lead@example.com',
        'source' => 'website_demo',
        'product' => 'broker',
        'status' => 'new',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stats.by_product.broker', 1)
            ->where('stats.by_source.website_demo', 1));
});

test('leads index supports sorting by name', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();

    Lead::create([
        'name' => 'Zulu Transport',
        'email' => 'zulu@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    Lead::create([
        'name' => 'Alpha Transport',
        'email' => 'alpha@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $this->actingAs($user)
        ->get(route('leads.index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leads/Index')
            ->where('leads.data.0.name', 'Alpha Transport')
            ->where('leads.data.1.name', 'Zulu Transport')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc'));
});

test('leads index can filter by assignee', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    Lead::create([
        'name' => 'Exec Lead',
        'email' => 'exec-filter@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $exec->id,
    ]);

    Lead::create([
        'name' => 'Unassigned Lead',
        'email' => 'unassigned-filter@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('leads.index', ['assigned_to' => $exec->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leads/Index')
            ->has('leads.data', 1)
            ->where('leads.data.0.email', 'exec-filter@example.com')
            ->where('filters.assigned_to', (string) $exec->id));

    $this->actingAs($admin)
        ->get(route('leads.index', ['assigned_to' => 'unassigned']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.email', 'unassigned-filter@example.com'));
});

test('leads index shows duration since last action', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Active Lead',
        'email' => 'active-lead@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    activity('lead')
        ->performedOn($lead)
        ->causedBy($admin)
        ->event('status_changed')
        ->log('Status changed to contacted');

    $this->travel(3)->days();

    $this->actingAs($admin)
        ->get(route('leads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Leads/Index')
            ->where('leads.data.0.email', 'active-lead@example.com')
            ->where('leads.data.0.last_action_ago', '3 days ago'));
});

test('leads index supports sorting by last action', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $older = Lead::create([
        'name' => 'Older Action Lead',
        'email' => 'older-action@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $newer = Lead::create([
        'name' => 'Newer Action Lead',
        'email' => 'newer-action@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    activity('lead')->performedOn($older)->causedBy($admin)->log('Older update');
    $this->travel(1)->day();
    activity('lead')->performedOn($newer)->causedBy($admin)->log('Newer update');

    $this->actingAs($admin)
        ->get(route('leads.index', ['sort' => 'last_action_at', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('leads.data.0.email', 'newer-action@example.com')
            ->where('leads.data.1.email', 'older-action@example.com')
            ->where('filters.sort', 'last_action_at')
            ->where('filters.direction', 'desc'));
});

test('lead follow-up can be scheduled and filtered', function () {
    $this->travelBack();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Follow Up Lead',
        'email' => 'follow-up@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $this->actingAs($admin)
        ->patch(route('leads.update', $lead), [
            'next_follow_up_at' => now()->addDays(2)->toDateString(),
        ])
        ->assertRedirect();

    expect($lead->fresh()->next_follow_up_at?->toDateString())->toBe(now()->addDays(2)->toDateString());

    $lead->update(['next_follow_up_at' => '2026-07-08']);

    expect($lead->fresh()->toArray()['next_follow_up_at'])->toBe('2026-07-08');

    $overdue = Lead::create([
        'name' => 'Overdue Lead',
        'email' => 'overdue-follow-up@example.com',
        'source' => 'manual',
        'status' => 'new',
        'next_follow_up_at' => now()->subDay()->toDateString(),
    ]);

    $this->actingAs($admin)
        ->get(route('leads.index', ['follow_up' => 'overdue', 'search' => 'overdue-follow-up@example.com']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('leads.data', 1)
            ->where('leads.data.0.email', 'overdue-follow-up@example.com')
            ->where('leads.data.0.follow_up_status', 'overdue'));
});

test('follow-up filters are mutually exclusive', function () {
    $this->travelTo('2026-06-15 10:00:00');

    Lead::query()->delete();

    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    Lead::create([
        'name' => 'Overdue',
        'email' => 'exclusive-overdue@example.com',
        'source' => 'manual',
        'status' => 'new',
        'next_follow_up_at' => now()->subDay()->toDateString(),
    ]);

    Lead::create([
        'name' => 'Due Today',
        'email' => 'exclusive-due-today@example.com',
        'source' => 'manual',
        'status' => 'new',
        'next_follow_up_at' => now()->toDateString(),
    ]);

    Lead::create([
        'name' => 'Upcoming',
        'email' => 'exclusive-upcoming@example.com',
        'source' => 'manual',
        'status' => 'new',
        'next_follow_up_at' => now()->addDays(2)->toDateString(),
    ]);

    Lead::create([
        'name' => 'No Follow Up',
        'email' => 'exclusive-none@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $overdueEmails = collect(
        $this->actingAs($admin)
            ->get(route('leads.index', ['follow_up' => 'overdue']))
            ->original
            ->getData()['page']['props']['leads']['data']
    )->pluck('email');

    expect($overdueEmails->all())->toBe(['exclusive-overdue@example.com']);

    $dueTodayEmails = collect(
        $this->actingAs($admin)
            ->get(route('leads.index', ['follow_up' => 'due_today']))
            ->original
            ->getData()['page']['props']['leads']['data']
    )->pluck('email');

    expect($dueTodayEmails->all())->toBe(['exclusive-due-today@example.com']);

    $upcomingEmails = collect(
        $this->actingAs($admin)
            ->get(route('leads.index', ['follow_up' => 'upcoming']))
            ->original
            ->getData()['page']['props']['leads']['data']
    )->pluck('email');

    expect($upcomingEmails->all())->toBe(['exclusive-upcoming@example.com']);

    $this->travelBack();
});

test('remarks are required when marking a lead as lost', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $lead = Lead::create([
        'name' => 'Lost Lead',
        'email' => 'lost-lead@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $this->actingAs($admin)
        ->from(route('leads.show', $lead))
        ->patch(route('leads.update', $lead), [
            'status' => 'lost',
            'remarks' => '',
        ])
        ->assertSessionHasErrors('remarks');

    expect($lead->fresh()->status->value)->toBe('new');

    $this->actingAs($admin)
        ->patch(route('leads.update', $lead), [
            'status' => 'lost',
            'remarks' => 'Budget constraints',
        ])
        ->assertRedirect();

    expect($lead->fresh())
        ->status->value->toBe('lost')
        ->remarks->toBe('Budget constraints')
        ->rejection_reason->toBe('Budget constraints');
});

test('sales executive only sees assigned leads', function () {
    $exec = User::query()->where('email', 'sales@truckbill.in')->first();

    Lead::create([
        'name' => 'Assigned Lead',
        'email' => 'assigned@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => $exec->id,
    ]);

    Lead::create([
        'name' => 'Other Lead',
        'email' => 'other@example.com',
        'source' => 'manual',
        'status' => 'new',
        'assigned_to' => User::query()->where('email', 'sales.manager@truckbill.in')->value('id'),
    ]);

    $this->actingAs($exec)
        ->getJson('/api/v1/admin/leads')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
