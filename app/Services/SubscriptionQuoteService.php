<?php

namespace App\Services;

use App\Enums\Product;

class SubscriptionQuoteService
{
    public function __construct(
        private readonly SubscriptionCatalogService $catalogService,
        private readonly SubscriptionSettingsService $settingsService,
    ) {}

    /**
     * Estimate an annual new-subscription quote from the catalog (no company usage checks).
     *
     * @param  array{
     *     product: string,
     *     addon_branches?: int,
     *     addon_users?: int,
     *     addon_trucks?: int,
     *     addon_lr_credits?: int
     * }  $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $product = Product::from($input['product']);
        $catalog = $this->catalogService->catalogForProduct($product->value);

        if ($catalog === null) {
            abort(404, 'Subscription catalog not found for product.');
        }

        $catalogArray = $catalog->toCatalogArray();
        $gstRate = (float) $this->settingsService->currentSettings()['gst_rate_percent'];
        $features = $this->productFeatures($product);

        $addonBranches = max(0, (int) ($input['addon_branches'] ?? 0));
        $addonUsers = max(0, (int) ($input['addon_users'] ?? 0));
        $addonTrucks = $features['trucks'] ? max(0, (int) ($input['addon_trucks'] ?? 0)) : 0;
        $addonLrCredits = $features['lr_credits'] ? max(0, (int) ($input['addon_lr_credits'] ?? 0)) : 0;

        $lineItems = [
            [
                'label' => 'Annual base plan',
                'amount' => round((float) $catalogArray['base_price'], 2),
            ],
        ];

        if ($addonBranches > 0) {
            $lineItems[] = [
                'label' => "Branch add-ons ({$addonBranches})",
                'amount' => round($addonBranches * (float) $catalogArray['addon_branch_price'], 2),
            ];
        }

        if ($addonUsers > 0) {
            $lineItems[] = [
                'label' => "User add-ons ({$addonUsers})",
                'amount' => round($addonUsers * (float) $catalogArray['addon_user_price'], 2),
            ];
        }

        if ($addonTrucks > 0) {
            $lineItems[] = [
                'label' => "Vehicle add-ons ({$addonTrucks})",
                'amount' => round($addonTrucks * (float) $catalogArray['addon_truck_price'], 2),
            ];
        }

        if ($addonLrCredits > 0) {
            $lineItems[] = [
                'label' => "LR credit add-ons ({$addonLrCredits})",
                'amount' => 0.0,
            ];
        }

        $subtotal = round(collect($lineItems)->sum('amount'), 2);
        $gstAmount = round($subtotal * ($gstRate / 100), 2);
        $total = round($subtotal + $gstAmount, 2);

        return [
            'product' => $product->value,
            'product_label' => $product->label(),
            'features' => $features,
            'line_items' => $lineItems,
            'subtotal' => $subtotal,
            'gst_amount' => $gstAmount,
            'total_amount' => $total,
            'gst_rate_percent' => $gstRate,
            'catalog' => $catalogArray,
            'quantities' => [
                'addon_branches' => $addonBranches,
                'addon_users' => $addonUsers,
                'addon_trucks' => $addonTrucks,
                'addon_lr_credits' => $addonLrCredits,
            ],
            'included' => [
                'branches' => $catalogArray['included_branches'],
                'users_per_branch' => $catalogArray['users_per_branch'],
                'lr_credits' => $catalogArray['included_lr_credits'],
                'trucks' => $catalogArray['included_trucks'],
            ],
        ];
    }

    /** @return array{lr_credits: bool, trucks: bool} */
    public function productFeatures(Product $product): array
    {
        return match ($product) {
            Product::Broker => ['lr_credits' => true, 'trucks' => false],
            Product::Parchun => ['lr_credits' => false, 'trucks' => false],
            Product::OwnFleet => ['lr_credits' => false, 'trucks' => true],
        };
    }
}
