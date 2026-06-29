<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\SubscriptionPlanCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'catalog' => SubscriptionPlanCatalog::query()->orderBy('product')->get(),
            'products' => collect(Product::cases())->map(fn (Product $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function update(Request $request, string $product): JsonResponse
    {
        $catalog = SubscriptionPlanCatalog::query()->where('product', $product)->firstOrFail();

        $validated = $request->validate([
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'included_branches' => ['sometimes', 'integer', 'min:1'],
            'users_per_branch' => ['sometimes', 'integer', 'min:1'],
            'included_lr_credits' => ['sometimes', 'integer', 'min:0'],
            'included_trucks' => ['sometimes', 'integer', 'min:0'],
            'addon_branch_price' => ['sometimes', 'numeric', 'min:0'],
            'addon_user_price' => ['sometimes', 'numeric', 'min:0'],
            'addon_truck_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'effective_from' => ['sometimes', 'date'],
        ]);

        $catalog->update($validated);

        return response()->json(['catalog' => $catalog->fresh()]);
    }
}
