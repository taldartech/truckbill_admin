<?php

namespace App\Http\Controllers;

use App\Enums\Product;
use App\Models\PromoCode;
use App\Services\ProductApiClient;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionWebController extends Controller
{
    public function __construct(
        private readonly ProductApiClient $productApi,
        private readonly SubscriptionCatalogService $catalogService,
        private readonly SubscriptionSettingsService $settingsService,
    ) {}

    public function pricing(): Response
    {
        return Inertia::render('Subscriptions/Pricing', [
            'catalog' => $this->catalogService->latestCatalogRows(),
            'products' => collect(Product::cases())->map(fn (Product $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function updatePricing(Request $request, string $product): RedirectResponse
    {
        $validated = $request->validate([
            'base_price' => ['required', 'numeric', 'min:0'],
            'included_branches' => ['required', 'integer', 'min:1'],
            'users_per_branch' => ['required', 'integer', 'min:1'],
            'included_lr_credits' => ['required', 'integer', 'min:0'],
            'included_trucks' => ['required', 'integer', 'min:0'],
            'addon_branch_price' => ['required', 'numeric', 'min:0'],
            'addon_user_price' => ['required', 'numeric', 'min:0'],
            'addon_truck_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = $this->catalogService->storeForProduct($product, $validated, $request->user()?->id);

        return back()->with(
            'success',
            $row ? 'Pricing updated.' : 'No changes to save.',
        );
    }

    public function settings(): Response
    {
        $current = $this->settingsService->currentSettings();
        $meta = $this->settingsService->currentMeta();

        return Inertia::render('Subscriptions/Settings', [
            'grace_period_days' => (int) $current['grace_period_days'],
            'gst_rate_percent' => (float) $current['gst_rate_percent'],
            'effective_from' => $meta['effective_from'],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:365'],
            'gst_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $row = $this->settingsService->store($validated, $request->user()?->id);

        return back()->with(
            'success',
            $row ? 'Subscription settings updated.' : 'No changes to save.',
        );
    }

    public function promoCodes(): Response
    {
        return Inertia::render('Subscriptions/PromoCodes', [
            'promo_codes' => PromoCode::query()->orderByDesc('created_at')->get(),
            'products' => $this->productOptions(),
        ]);
    }

    public function createPromoCode(): Response
    {
        return Inertia::render('Subscriptions/PromoCodeCreate', [
            'products' => $this->productOptions(),
            'discount_types' => [
                ['value' => 'percentage', 'label' => 'Percentage'],
                ['value' => 'absolute', 'label' => 'Absolute (₹)'],
            ],
        ]);
    }

    public function storePromoCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')],
            'discount_type' => ['required', Rule::in(['percentage', 'absolute'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'applicable_products' => ['required', 'array', 'min:1'],
            'applicable_products.*' => [Rule::enum(Product::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        PromoCode::create(array_merge($validated, [
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()?->id,
        ]));

        return redirect()->route('subscriptions.promo-codes')->with('success', 'Promo code created.');
    }

    public function editPromoCode(PromoCode $promoCode): Response
    {
        return Inertia::render('Subscriptions/PromoCodeEdit', [
            'promo_code' => [
                'id' => $promoCode->id,
                'code' => $promoCode->code,
                'discount_type' => $promoCode->discount_type,
                'discount_value' => (float) $promoCode->discount_value,
                'applicable_products' => $promoCode->applicable_products ?? [],
                'start_date' => $promoCode->start_date->toDateString(),
                'end_date' => $promoCode->end_date?->toDateString(),
                'max_uses' => $promoCode->max_uses,
                'uses_count' => $promoCode->uses_count,
                'is_active' => $promoCode->is_active,
            ],
            'products' => $this->productOptions(),
            'discount_types' => [
                ['value' => 'percentage', 'label' => 'Percentage'],
                ['value' => 'absolute', 'label' => 'Absolute (₹)'],
            ],
        ]);
    }

    public function updatePromoCode(Request $request, PromoCode $promoCode): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($promoCode->id)],
            'discount_type' => ['required', Rule::in(['percentage', 'absolute'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'applicable_products' => ['required', 'array', 'min:1'],
            'applicable_products.*' => [Rule::enum(Product::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $promoCode->update(array_merge($validated, [
            'code' => strtoupper($validated['code']),
        ]));

        return redirect()->route('subscriptions.promo-codes')->with('success', 'Promo code updated.');
    }

    public function companies(): Response
    {
        $items = [];

        foreach (Product::cases() as $product) {
            try {
                $response = $this->productApi->get('/api/admin/subscriptions', $product, ['per_page' => 50]);
                foreach ($response['data'] ?? [] as $row) {
                    $items[] = array_merge($row, ['product' => $product->value, 'product_label' => $product->label()]);
                }
            } catch (\Throwable) {
                // Product API may be unavailable in local dev.
            }
        }

        return Inertia::render('Subscriptions/Companies', [
            'companies' => $items,
            'products' => collect(Product::cases())->map(fn (Product $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function companyShow(string $product, int $id): Response
    {
        $productEnum = Product::from($product);
        $detail = $this->productApi->get("/api/admin/subscriptions/{$id}", $productEnum);

        return Inertia::render('Subscriptions/CompanyShow', [
            'product' => $product,
            'product_label' => $productEnum->label(),
            'subscription' => $detail,
        ]);
    }

    public function companyUpdate(Request $request, string $product, int $id): RedirectResponse
    {
        $productEnum = Product::from($product);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['trial', 'active', 'expired', 'cancelled'])],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date'],
            'addon_branches' => ['sometimes', 'integer', 'min:0'],
            'addon_users' => ['sometimes', 'integer', 'min:0'],
            'addon_trucks' => ['sometimes', 'integer', 'min:0'],
            'addon_lr_credits' => ['sometimes', 'integer', 'min:0'],
            'lr_credits_remaining' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->productApi->patch("/api/admin/subscriptions/{$id}", $productEnum, $validated);

        return back()->with('success', 'Subscription updated.');
    }

    /** @return list<array{value: string, label: string}> */
    private function productOptions(): array
    {
        return collect(Product::cases())->map(fn (Product $p) => [
            'value' => $p->value,
            'label' => $p->label(),
        ])->all();
    }
}
