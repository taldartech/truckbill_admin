<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plan_catalog')) {
            return;
        }

        if (Schema::hasColumn('subscription_plan_catalog', 'base_price')) {
            return;
        }

        Schema::table('subscription_plan_catalog', function (Blueprint $table) {
            $table->decimal('base_price', 12, 2)->nullable()->after('product');
            $table->decimal('addon_branch_price', 12, 2)->nullable()->after('included_trucks');
            $table->decimal('addon_user_price', 12, 2)->nullable()->after('addon_branch_price');
            $table->decimal('addon_truck_price', 12, 2)->nullable()->after('addon_user_price');
        });

        if (Schema::hasColumn('subscription_plan_catalog', 'base_price_paise')) {
            DB::table('subscription_plan_catalog')->orderBy('id')->each(function ($row) {
                DB::table('subscription_plan_catalog')->where('id', $row->id)->update([
                    'base_price' => round($row->base_price_paise / 100, 2),
                    'addon_branch_price' => round($row->addon_branch_price_paise / 100, 2),
                    'addon_user_price' => round($row->addon_user_price_paise / 100, 2),
                    'addon_truck_price' => round($row->addon_truck_price_paise / 100, 2),
                ]);
            });

            Schema::table('subscription_plan_catalog', function (Blueprint $table) {
                $table->dropColumn([
                    'base_price_paise',
                    'addon_branch_price_paise',
                    'addon_user_price_paise',
                    'addon_truck_price_paise',
                ]);
            });
        }

        if (Schema::hasColumn('promo_codes', 'discount_value')) {
            DB::statement('ALTER TABLE promo_codes MODIFY discount_value DECIMAL(12,2) NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_plan_catalog')) {
            return;
        }

        if (! Schema::hasColumn('subscription_plan_catalog', 'base_price')) {
            return;
        }

        if (Schema::hasColumn('subscription_plan_catalog', 'base_price_paise')) {
            return;
        }

        Schema::table('subscription_plan_catalog', function (Blueprint $table) {
            $table->unsignedBigInteger('base_price_paise')->nullable()->after('product');
            $table->unsignedBigInteger('addon_branch_price_paise')->nullable()->after('included_trucks');
            $table->unsignedBigInteger('addon_user_price_paise')->nullable()->after('addon_branch_price_paise');
            $table->unsignedBigInteger('addon_truck_price_paise')->nullable()->after('addon_user_price_paise');
        });

        DB::table('subscription_plan_catalog')->orderBy('id')->each(function ($row) {
            DB::table('subscription_plan_catalog')->where('id', $row->id)->update([
                'base_price_paise' => (int) round(((float) $row->base_price) * 100),
                'addon_branch_price_paise' => (int) round(((float) $row->addon_branch_price) * 100),
                'addon_user_price_paise' => (int) round(((float) $row->addon_user_price) * 100),
                'addon_truck_price_paise' => (int) round(((float) $row->addon_truck_price) * 100),
            ]);
        });

        Schema::table('subscription_plan_catalog', function (Blueprint $table) {
            $table->dropColumn([
                'base_price',
                'addon_branch_price',
                'addon_user_price',
                'addon_truck_price',
            ]);
        });

        if (Schema::hasColumn('promo_codes', 'discount_value')) {
            DB::statement('ALTER TABLE promo_codes MODIFY discount_value DECIMAL(8,2) NOT NULL');
        }
    }
};
