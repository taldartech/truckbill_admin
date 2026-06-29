<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_settings', function (Blueprint $table) {
            $table->id();
            $table->json('settings');
            $table->date('effective_from');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $gstRate = 18.0;

        if (Schema::hasTable('gst_settings')) {
            $latestGst = DB::table('gst_settings')->orderByDesc('effective_from')->first();
            if ($latestGst !== null) {
                $gstRate = (float) $latestGst->rate_percent;
            }
        }

        DB::table('subscription_settings')->insert([
            'settings' => json_encode([
                'grace_period_days' => 15, // 15 days
                'gst_rate_percent' => $gstRate,
            ]),
            'effective_from' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('gst_settings')) {
            Schema::dropIfExists('gst_settings');
        }
    }

    public function down(): void
    {
        Schema::create('gst_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('rate_percent', 5, 2);
            $table->date('effective_from');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (Schema::hasTable('subscription_settings')) {
            $latest = DB::table('subscription_settings')->orderByDesc('effective_from')->first();

            if ($latest !== null) {
                $settings = json_decode($latest->settings, true) ?? [];

                DB::table('gst_settings')->insert([
                    'rate_percent' => (float) ($settings['gst_rate_percent'] ?? 18.0),
                    'effective_from' => $latest->effective_from,
                    'updated_by' => $latest->updated_by,
                    'created_at' => $latest->created_at,
                    'updated_at' => $latest->updated_at,
                ]);
            }
        }

        Schema::dropIfExists('subscription_settings');
    }
};
