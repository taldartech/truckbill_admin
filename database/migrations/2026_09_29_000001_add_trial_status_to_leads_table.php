<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->enum('status', [
                'new',
                'contacted',
                'qualified',
                'demo_scheduled',
                'proposal_sent',
                'trial',
                'converted',
                'lost',
            ])->default('new')->change();
        });
    }

    public function down(): void
    {
        DB::table('leads')->where('status', 'trial')->update(['status' => 'converted']);

        Schema::table('leads', function (Blueprint $table) {
            $table->enum('status', [
                'new',
                'contacted',
                'qualified',
                'demo_scheduled',
                'proposal_sent',
                'converted',
                'lost',
            ])->default('new')->change();
        });
    }
};
