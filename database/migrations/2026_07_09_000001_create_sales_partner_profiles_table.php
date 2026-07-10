<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_partner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('mobile', 20);
            $table->string('working_location')->nullable();
            $table->string('current_company_name')->nullable();
            $table->string('pan_number', 10)->nullable();
            $table->string('aadhar_number', 12)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('ifsc_code', 11)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_partner_profiles');
    }
};
