<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_invitation_id')->constrained()->cascadeOnDelete();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('policy_accepted_at')->nullable();
            $table->string('policy_version')->nullable();
            $table->timestamp('password_set_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_sessions');
    }
};
