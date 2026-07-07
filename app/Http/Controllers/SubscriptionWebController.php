<?php

namespace App\Http\Controllers;

use App\Enums\Product;
use App\Models\PromoCode;
use App\Services\ProductApiClient;
use App\Services\SubscriptionCatalogService;
use App\Services\SubscriptionSettingsService;
use Illuminate\Http\JsonResponse;
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
        $this->authorizeView();

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
        $this->authorizePricing();

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
        $this->authorizeView();

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
        $this->authorizeSettings();

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
        $this->authorizeView();

        return Inertia::render('Subscriptions/PromoCodes', [
            'promo_codes' => PromoCode::query()->orderByDesc('created_at')->get(),
            'products' => $this->productOptions(),
        ]);
    }

    public function createPromoCode(): Response
    {
        $this->authorizePromoCodes();

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
        $this->authorizePromoCodes();

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
        $this->authorizePromoCodes();

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
        $this->authorizePromoCodes();

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
        $this->authorizeView();

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

    public function companyShow(string $product, string $id): Response
    {
        $this->authorizeView();

        $productEnum = Product::from($product);
        $detail = $this->productApi->get("/api/admin/subscriptions/{$id}", $productEnum);
        $catalog = $this->catalogService->catalogForProduct($product);

        return Inertia::render('Subscriptions/CompanyShow', [
            'product' => $product,
            'product_label' => $productEnum->label(),
            'subscription' => $detail,
            'catalog' => $catalog?->toCatalogArray(),
            'features' => $this->productFeatures($productEnum),
        ]);
    }

    public function companyUpdate(Request $request, string $product, string $id): RedirectResponse
    {
        $this->authorizeCompanies();

        $productEnum = Product::from($product);

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['trial', 'active', 'expired', 'cancelled'])],
            'starts_on' => ['sometimes', 'date'],
            'ends_on' => ['sometimes', 'date'],
            'trial_ends_on' => ['sometimes', 'nullable', 'date'],
            'included_branches' => ['sometimes', 'integer', 'min:1'],
            'users_per_branch' => ['sometimes', 'integer', 'min:1'],
            'included_lr_credits' => ['sometimes', 'integer', 'min:0'],
            'included_trucks' => ['sometimes', 'integer', 'min:0'],
            'addon_branches' => ['sometimes', 'integer', 'min:0'],
            'addon_users' => ['sometimes', 'integer', 'min:0'],
            'addon_trucks' => ['sometimes', 'integer', 'min:0'],
            'addon_lr_credits' => ['sometimes', 'integer', 'min:0'],
            'lr_credits_remaining' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        try {
            $this->productApi->patch("/api/admin/subscriptions/{$id}", $productEnum, $validated);
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), '422')) {
                return back()->with('success', 'No changes to save.');
            }

            throw $exception;
        }

        return back()->with('success', 'Subscription updated.');
    }

    public function companyQuote(Request $request, string $product, string $id): JsonResponse
    {
        $this->authorizeCompanies();

        $productEnum = Product::from($product);

        $validated = $request->validate([
            'purchase_type' => ['required', Rule::in(['renewal', 'addon'])],
            'addon_branches' => ['nullable', 'integer', 'min:0'],
            'addon_users' => ['nullable', 'integer', 'min:0'],
            'addon_trucks' => ['nullable', 'integer', 'min:0'],
            'addon_lr_credits' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->productApi->post("/api/admin/subscriptions/{$id}/quote", $productEnum, $validated),
        );
    }

    public function companyOfflinePayment(Request $request, string $product, string $id): RedirectResponse
    {
        $this->authorizeCompanies();

        $productEnum = Product::from($product);

        $validated = $request->validate([
            'purchase_type' => ['required', Rule::in(['renewal', 'addon'])],
            'addon_branches' => ['nullable', 'integer', 'min:0'],
            'addon_users' => ['nullable', 'integer', 'min:0'],
            'addon_trucks' => ['nullable', 'integer', 'min:0'],
            'addon_lr_credits' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $validated['admin_user_id'] = $request->user()?->id;

        try {
            $this->productApi->post("/api/admin/subscriptions/{$id}/offline-payment", $productEnum, $validated);
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), '422')) {
                $message = 'Unable to record offline payment.';
                if (preg_match('/Product API error: 422 (.+)/', $exception->getMessage(), $matches)) {
                    $payload = json_decode($matches[1], true);
                    $message = $payload['message'] ?? $message;
                }

                return back()->withErrors(['offline' => $message]);
            }

            throw $exception;
        }

        return back()->with('success', 'Offline payment recorded and plan updated.');
    }

    private function authorizeView(): void
    {
        abort_unless(auth()->user()?->can('subscriptions.view'), 403);
    }

    private function authorizePricing(): void
    {
        abort_unless(auth()->user()?->can('subscriptions.pricing'), 403);
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth()->user()?->can('subscriptions.settings'), 403);
    }

    private function authorizePromoCodes(): void
    {
        abort_unless(auth()->user()?->can('subscriptions.promo_codes'), 403);
    }

    private function authorizeCompanies(): void
    {
        abort_unless(auth()->user()?->can('subscriptions.companies'), 403);
    }

    /** @return array{lr_credits: bool, trucks: bool} */
    private function productFeatures(Product $product): array
    {
        return match ($product) {
            Product::Broker => ['lr_credits' => true, 'trucks' => false],
            Product::Parchun => ['lr_credits' => false, 'trucks' => false],
            Product::OwnFleet => ['lr_credits' => false, 'trucks' => true],
        };
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
