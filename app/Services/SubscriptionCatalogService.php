<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\SubscriptionPlanCatalog;
use Illuminate\Support\Str;

class SubscriptionCatalogService
{
    public function __construct(
        private readonly SubscriptionSettingsService $settingsService,
    ) {}

    public function catalogForProduct(string $product): ?SubscriptionPlanCatalog
    {
        return SubscriptionPlanCatalog::query()
            ->where('product', $product)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();
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
}
