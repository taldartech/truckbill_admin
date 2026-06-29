<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Services\SubscriptionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionCatalogController extends Controller
{
    public function __construct(
        private readonly SubscriptionCatalogService $catalogService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'catalog' => $this->catalogService->latestCatalogRows(),
            'products' => collect(Product::cases())->map(fn (Product $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function update(Request $request, string $product): JsonResponse
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

        $row = $this->catalogService->storeForProduct(
            $product,
            $validated,
            $request->user()?->id,
        );

        if ($row === null) {
            $current = $this->catalogService->catalogForProduct($product)
                ?? $this->catalogService->latestCatalogRows()->firstWhere('product', $product);

            return response()->json([
                'message' => 'No changes to save.',
                'catalog' => $current,
            ]);
        }

        return response()->json(['catalog' => $row]);
    }
}
