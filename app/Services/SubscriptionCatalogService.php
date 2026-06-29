<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\SubscriptionPlanCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubscriptionCatalogService
{
    public function __construct(
        private readonly SubscriptionSettingsService $settingsService,
    ) {}

    /** @return Collection<int, SubscriptionPlanCatalog> */
    public function latestCatalogRows(): Collection
    {
        return SubscriptionPlanCatalog::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('product')
            ->sortBy('product')
            ->values();
    }

    public function catalogForProduct(string $product): ?SubscriptionPlanCatalog
    {
        return SubscriptionPlanCatalog::query()
            ->where('product', $product)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeForProduct(string $product, array $data, ?int $updatedBy = null): ?SubscriptionPlanCatalog
    {
        $current = SubscriptionPlanCatalog::query()
            ->where('product', $product)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $attributes = $this->normalizedCatalogData($data);

        if ($current !== null && $this->catalogDataEquals($current, $attributes)) {
            return null;
        }

        return SubscriptionPlanCatalog::create(array_merge($attributes, [
            'product' => $product,
            'effective_from' => now()->toDateString(),
            'updated_by' => $updatedBy,
        ]));
    }

    public function catalogPayload(string $product): array
    {
        $catalog = $this->catalogForProduct($product);

        if ($catalog === null) {
            abort(404, 'Subscription catalog not found for product.');
        }

        $settings = $this->settingsService->currentSettings();

        return [
            'catalog' => $catalog->toCatalogArray(),
            'gst_rate_percent' => (float) $settings['gst_rate_percent'],
            'settings' => $settings,
        ];
    }

    public function validatePromo(string $product, string $code): array
    {
        $promo = PromoCode::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->where('is_active', true)
            ->first();

        if ($promo === null) {
            return ['valid' => false, 'message' => 'Invalid promo code.'];
        }

        if (! $promo->appliesToProduct($product)) {
            return ['valid' => false, 'message' => 'Promo code is not valid for this product.'];
        }

        $today = now()->toDateString();

        if ($promo->start_date->toDateString() > $today) {
            return ['valid' => false, 'message' => 'Promo code is not yet active.'];
        }

        if ($promo->end_date !== null && $promo->end_date->toDateString() < $today) {
            return ['valid' => false, 'message' => 'Promo code has expired.'];
        }

        if ($promo->max_uses !== null && $promo->uses_count >= $promo->max_uses) {
            return ['valid' => false, 'message' => 'Promo code usage limit reached.'];
        }

        return [
            'valid' => true,
            'promo' => [
                'code' => $promo->code,
                'discount_type' => $promo->discount_type,
                'discount_value' => (float) $promo->discount_value,
            ],
        ];
    }

    public function incrementPromoUse(string $code): void
    {
        PromoCode::query()
            ->whereRaw('LOWER(code) = ?', [Str::lower($code)])
            ->increment('uses_count');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedCatalogData(array $data): array
    {
        return [
            'base_price' => round((float) $data['base_price'], 2),
            'included_branches' => (int) $data['included_branches'],
            'users_per_branch' => (int) $data['users_per_branch'],
            'included_lr_credits' => (int) $data['included_lr_credits'],
            'included_trucks' => (int) $data['included_trucks'],
            'addon_branch_price' => round((float) $data['addon_branch_price'], 2),
            'addon_user_price' => round((float) $data['addon_user_price'], 2),
            'addon_truck_price' => round((float) $data['addon_truck_price'], 2),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function catalogDataEquals(SubscriptionPlanCatalog $current, array $data): bool
    {
        $currentData = $this->normalizedCatalogData([
            'base_price' => $current->base_price,
            'included_branches' => $current->included_branches,
            'users_per_branch' => $current->users_per_branch,
            'included_lr_credits' => $current->included_lr_credits,
            'included_trucks' => $current->included_trucks,
            'addon_branch_price' => $current->addon_branch_price,
            'addon_user_price' => $current->addon_user_price,
            'addon_truck_price' => $current->addon_truck_price,
            'is_active' => $current->is_active,
        ]);

        foreach ($currentData as $field => $value) {
            if ($currentData[$field] !== $data[$field]) {
                return false;
            }
        }

        return true;
    }
}
