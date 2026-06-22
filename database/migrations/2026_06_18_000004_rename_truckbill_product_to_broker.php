<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['leads', 'onboarding_invitations', 'provisioning_logs', 'companies'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::table($table)
                ->where('product', 'truckbill')
                ->update(['product' => 'broker']);
        }
    }

    public function down(): void
    {
        foreach (['leads', 'onboarding_invitations', 'provisioning_logs', 'companies'] as $table) {
            if (! $this->tableExists($table)) {
                continue;
            }

            DB::table($table)
                ->where('product', 'broker')
                ->update(['product' => 'truckbill']);
        }
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }
};
