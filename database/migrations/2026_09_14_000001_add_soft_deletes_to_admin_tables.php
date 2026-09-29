<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'users',
        'leads',
        'lead_notes',
        'onboarding_invitations',
        'onboarding_sessions',
        'provisioning_logs',
        'email_otps',
        'promo_codes',
        'subscription_plan_catalog',
        'subscription_settings',
        'sales_partner_profiles',
        'partner_onboarding_invitations',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Allow reusing emails/codes after soft delete (uniqueness enforced in app for non-deleted rows).
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->index('code');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropIndex(['code']);
            $table->unique('code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });

        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
