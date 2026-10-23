<?php

use App\Models\Lead;
use App\Models\PromoCode;
use App\Models\ProvisioningLog;
use App\Models\SalesPartnerProfile;
use App\Models\User;
use App\Services\OnboardingInvitationService;
use App\Services\PartnerOnboardingService;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SubscriptionCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed([
        RoleAndPermissionSeeder::class,
        AdminUserSeeder::class,
        SubscriptionCatalogSeeder::class,
    ]);
});

test('domain models strictly prohibit hard deletion via SoftDeletesOnly trait', function () {
    $lead = Lead::create([
        'name' => 'Hard Delete Prevention',
        'email' => 'noforce@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    expect(fn () => $lead->forceDelete())
        ->toThrow(LogicException::class, 'Hard deletes are not allowed. Use soft delete instead.');
});

test('database enforces unique constraint on provisioning log idempotency key', function () {
    $lead = Lead::create([
        'name' => 'Log Lead',
        'email' => 'log@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);
    $invitation = app(OnboardingInvitationService::class)->createForLead($lead, 'broker', 1)['invitation'];

    ProvisioningLog::create([
        'onboarding_invitation_id' => $invitation->id,
        'idempotency_key' => 'IDEMP-12345',
        'product' => 'broker',
        'status' => 'pending',
    ]);

    expect(function () use ($invitation) {
        ProvisioningLog::create([
            'onboarding_invitation_id' => $invitation->id,
            'idempotency_key' => 'IDEMP-12345',
            'product' => 'broker',
            'status' => 'pending',
        ]);
    })->toThrow(QueryException::class);
});

test('transaction rolls back cleanly when partner onboarding encounters an error', function () {
    $service = app(PartnerOnboardingService::class);
    $user = User::create([
        'name' => 'Rollback User',
        'email' => 'rollback@example.com',
        'password' => Hash::make('secret'),
    ]);
    $user->assignRole('Sales Partner');

    $invitation = $service->createInvitation($user)['invitation'];

    $incompleteData = [
        'legal_name' => 'Failing Partner',
        'mobile' => '9800000000',
        'password' => 'NewSecret1!',
    ];

    try {
        DB::transaction(function () use ($service, $invitation, $incompleteData) {
            $service->complete($invitation, $incompleteData);
            throw new RuntimeException('Simulated catastrophic failure after complete step');
        });
    } catch (RuntimeException $e) {
        // Expected simulation
    }

    // Verify all changes were rolled back
    $user->refresh();
    expect($user->name)->toBe('Rollback User')
        ->and($invitation->fresh()->completed_at)->toBeNull()
        ->and(SalesPartnerProfile::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('soft-deletes allow recovery and do not block reusing identifier', function () {
    $lead = Lead::create([
        'name' => 'Archived Customer',
        'email' => 'archive@example.com',
        'source' => 'manual',
        'status' => 'new',
    ]);

    $leadId = $lead->id;

    // 1. Soft-delete
    $lead->delete();

    // 2. Normal queries exclude soft-deleted record
    expect(Lead::query()->where('id', $leadId)->first())->toBeNull();

    // 3. withTrashed finds soft-deleted record
    $trashed = Lead::withTrashed()->where('id', $leadId)->first();
    expect($trashed)->not->toBeNull()
        ->and($trashed->trashed())->toBeTrue();

    // 4. Restore recovers the record
    $trashed->restore();
    expect(Lead::query()->where('id', $leadId)->first())->not->toBeNull()
        ->and($lead->fresh()->trashed())->toBeFalse();
});

test('soft-deleted promo codes can have their code reused by active promo codes', function () {
    $admin = User::query()->where('email', 'admin@truckbill.in')->first();

    $promo = PromoCode::create([
        'code' => 'REUSEDCODE',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'applicable_products' => ['broker'],
        'start_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    // Soft delete first code
    $promo->delete();

    // Storing new promo code with same code name succeeds because deleted_at is null filter is applied
    $this->actingAs($admin)
        ->post(route('subscriptions.promo-codes.store'), [
            'code' => 'REUSEDCODE',
            'discount_type' => 'percentage',
            'discount_value' => 20,
            'applicable_products' => ['broker'],
            'start_date' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Promo code created.');

    expect(PromoCode::query()->where('code', 'REUSEDCODE')->count())->toBe(1)
        ->and(PromoCode::withTrashed()->where('code', 'REUSEDCODE')->count())->toBe(2);
});
