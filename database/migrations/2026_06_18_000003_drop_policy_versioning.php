<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_sessions') && Schema::hasColumn('onboarding_sessions', 'policy_version')) {
            Schema::table('onboarding_sessions', function (Blueprint $table): void {
                $table->dropColumn('policy_version');
            });
        }

        Schema::dropIfExists('policy_versions');
    }

    public function down(): void
    {
        if (! Schema::hasTable('policy_versions')) {
            Schema::create('policy_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('version')->unique();
                $table->timestamp('effective_at');
                $table->json('documents');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('onboarding_sessions') && ! Schema::hasColumn('onboarding_sessions', 'policy_version')) {
            Schema::table('onboarding_sessions', function (Blueprint $table): void {
                $table->string('policy_version')->nullable()->after('policy_accepted_at');
            });
        }
    }
};
