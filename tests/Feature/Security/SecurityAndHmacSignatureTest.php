<?php

use App\Models\Lead;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
    config(['crm.products.broker.signing_secret' => 'broker-super-secret']);
});

test('product api middleware rejects request for unknown product with 404', function () {
    $this->getJson('/api/v1/products/invalidproduct/subscription-catalog')
        ->assertStatus(404)
        ->assertJsonPath('message', 'Unknown product.');
});

test('product api middleware rejects missing signature headers with 401', function () {
    $this->getJson('/api/v1/products/broker/subscription-catalog')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Missing signature headers.');
});

test('product api middleware rejects non-numeric timestamp with 401', function () {
    $this->withHeaders([
        'X-Timestamp' => 'not-a-timestamp',
        'X-Signature' => 'dummy-signature',
    ])->getJson('/api/v1/products/broker/subscription-catalog')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid timestamp.');
});

test('product api middleware rejects expired timestamp skew with 401', function () {
    // 301 seconds in the past
    $expiredTimestamp = (string) (time() - 301);
    $signature = hash_hmac('sha256', $expiredTimestamp.'.', 'broker-super-secret');

    $this->withHeaders([
        'X-Timestamp' => $expiredTimestamp,
        'X-Signature' => $signature,
    ])->getJson('/api/v1/products/broker/subscription-catalog')
        ->assertStatus(401)
        ->assertJsonPath('message', 'Request timestamp expired.');
});

test('product api middleware rejects tampered request payload or mismatched signature with 401', function () {
    $timestamp = (string) time();
    $tamperedSignature = hash_hmac('sha256', $timestamp.'.original', 'wrong-secret');

    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $tamperedSignature,
    ])->postJson('/api/v1/products/broker/promo-codes/validate', ['code' => 'ANY'])
        ->assertStatus(401)
        ->assertJsonPath('message', 'Invalid signature.');
});

test('product api middleware accepts valid hmac-signed requests', function () {
    $timestamp = (string) time();
    $body = json_encode(['code' => 'SAVE10']);
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'broker-super-secret');

    $this->withHeaders([
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
    ])->postJson('/api/v1/products/broker/promo-codes/validate', ['code' => 'SAVE10'])
        ->assertOk();
});

test('user model serialization hides sensitive credentials like passwords and 2fa secrets', function () {
    $user = User::query()->where('email', 'admin@truckbill.in')->first();
    $array = $user->toArray();

    expect($array)->not->toHaveKey('password')
        ->and($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes')
        ->and($array)->not->toHaveKey('remember_token');
});

test('lead search query handles sql injection attempts safely without error', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    Lead::create([
        'name' => 'Safe Lead',
        'email' => 'safe@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    // SQL Injection payload in search parameter
    $maliciousSearch = "' OR '1'='1' -- ";
    $response = $this->actingAs($admin)
        ->get(route('leads.index', ['search' => $maliciousSearch]))
        ->assertOk();

    // Verify it doesn't crash or return all records through injection
    $leads = $response->original->getData()['page']['props']['leads']['data'];
    expect($leads)->toBeEmpty();
});
