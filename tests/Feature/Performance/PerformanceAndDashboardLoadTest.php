<?php

use App\Models\Lead;
use App\Models\User;
use App\Services\SubscriptionCatalogService;
use App\Support\CrmDashboardStats;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('crm dashboard stats executes with minimal sql queries and under 100ms for large datasets', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    // Seed 120 leads with various statuses and products in bulk
    $statuses = ['new', 'contacted', 'qualified', 'trial', 'converted', 'lost'];
    $products = ['broker', 'ownfleet', 'parchun'];
    $sources = ['website_demo', 'google', 'referral', 'manual'];

    $records = [];
    for ($i = 1; $i <= 120; $i++) {
        $records[] = [
            'name' => "Lead {$i}",
            'email' => "lead{$i}@enterprise.com",
            'mobile' => '980000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
            'status' => $statuses[$i % count($statuses)],
            'product' => $products[$i % count($products)],
            'source' => $sources[$i % count($sources)],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Lead::insert($records);

    // Track query count and time
    DB::enableQueryLog();
    $startTime = microtime(true);

    $stats = CrmDashboardStats::build($admin);

    $elapsedMs = (microtime(true) - $startTime) * 1000;
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Assert query count is bounded and has no N+1 loop (at most 7 queries: status, product, source, total count, 3 follow-up filters)
    expect(count($queries))->toBeLessThanOrEqual(7)
        ->and($stats['total'])->toBeGreaterThanOrEqual(120)
        ->and($elapsedMs)->toBeLessThan(100.0);
});

test('crm paginated lead listing with filters executes under 150ms', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $records = [];
    for ($i = 1; $i <= 50; $i++) {
        $records[] = [
            'name' => "Company {$i}",
            'email' => "company{$i}@logistics.in",
            'status' => 'new',
            'product' => 'broker',
            'source' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Lead::insert($records);

    $startTime = microtime(true);

    $this->actingAs($admin)
        ->get(route('leads.index', ['status' => 'new', 'product' => 'broker']))
        ->assertOk();

    $elapsedMs = (microtime(true) - $startTime) * 1000;
    expect($elapsedMs)->toBeLessThan(150.0);
});

test('subscription catalog latest rows query executes in a single database query', function () {
    $catalogService = app(SubscriptionCatalogService::class);

    DB::enableQueryLog();
    $rows = $catalogService->latestCatalogRows();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect(count($queries))->toBe(1)
        ->and($rows->count())->toBe(3);
});
