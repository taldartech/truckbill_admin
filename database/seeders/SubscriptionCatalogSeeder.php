<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlanCatalog;
use App\Models\SubscriptionSetting;
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
            $exists = SubscriptionPlanCatalog::query()
                ->where('product', $row['product'])
                ->exists();

            if ($exists) {
                continue;
            }

            SubscriptionPlanCatalog::create(array_merge($row, [
                'is_active' => true,
                'effective_from' => $effectiveFrom,
            ]));
        }

        if (! SubscriptionSetting::query()->exists()) {
            SubscriptionSetting::create([
                'settings' => [
                    'grace_period_days' => 7,
                    'gst_rate_percent' => 18.0,
                ],
                'effective_from' => $effectiveFrom,
            ]);
        }
    }
}
