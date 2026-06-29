<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subscription_plan_catalog')) {
            return;
        }

        Schema::table('subscription_plan_catalog', function (Blueprint $table) {
            $table->dropUnique(['product']);
            $table->index(['product', 'effective_from']);
            $table->foreignId('updated_by')->nullable()->after('effective_from')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subscription_plan_catalog')) {
            return;
        }

        Schema::table('subscription_plan_catalog', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropIndex(['product', 'effective_from']);
            $table->unique('product');
        });
    }
};
