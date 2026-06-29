<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('product')->unique();
            $table->decimal('base_price', 12, 2);
            $table->unsignedSmallInteger('included_branches')->default(2);
            $table->unsignedSmallInteger('users_per_branch')->default(3);
            $table->unsignedInteger('included_lr_credits')->default(0);
            $table->unsignedInteger('included_trucks')->default(0);
            $table->decimal('addon_branch_price', 12, 2);
            $table->decimal('addon_user_price', 12, 2);
            $table->decimal('addon_truck_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_catalog');
    }
};
