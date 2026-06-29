<?php

namespace Database\Seeders;

use App\Models\GstSetting;
use App\Models\SubscriptionPlanCatalog;
use Illuminate\Database\Seeder;

class SubscriptionCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $effectiveFrom = now()->toDateString();

        $defaults = [
            [
                'product' => 'parchun',
                'base_price' => 20000.00,
                'included_branches' => 2,
                'users_per_branch' => 3,
                'included_lr_credits' => 0,
                'included_trucks' => 0,
                'addon_branch_price' => 6500.00,
                'addon_user_price' => 500.00,
                'addon_truck_price' => 0,
            ],
            [
                'product' => 'broker',
                'base_price' => 20000.00,
                'included_branches' => 2,
                'users_per_branch' => 3,
                'included_lr_credits' => 3600,
                'included_trucks' => 0,
                'addon_branch_price' => 6500.00,
                'addon_user_price' => 500.00,
                'addon_truck_price' => 0,
            ],
            [
                'product' => 'ownfleet',
                'base_price' => 20000.00,
                'included_branches' => 2,
                'users_per_branch' => 3,
                'included_lr_credits' => 0,
                'included_trucks' => 50,
                'addon_branch_price' => 6500.00,
                'addon_user_price' => 500.00,
                'addon_truck_price' => 365.00,
            ],
        ];

        foreach ($defaults as $row) {
            SubscriptionPlanCatalog::updateOrCreate(
                ['product' => $row['product']],
                array_merge($row, [
                    'is_active' => true,
                    'effective_from' => $effectiveFrom,
                ])
            );
        }

        if (! GstSetting::query()->exists()) {
            GstSetting::create([
                'rate_percent' => 18,
                'effective_from' => $effectiveFrom,
            ]);
        }
    }
}
