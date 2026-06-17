<?php

namespace Database\Seeders;

use App\Models\PolicyVersion;
use Illuminate\Database\Seeder;

class PolicyVersionSeeder extends Seeder
{
    public function run(): void
    {
        PolicyVersion::query()->update(['is_active' => false]);

        PolicyVersion::create([
            'version' => '2026-06-01',
            'effective_at' => now(),
            'is_active' => true,
            'documents' => config('onboarding.policies'),
        ]);
    }
}
