<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionCatalogService $catalogService
    ) {}

    public function catalog(string $product): JsonResponse
    {
        return response()->json($this->catalogService->catalogPayload($product));
    }

    public function validatePromo(Request $request, string $product): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        return response()->json(
            $this->catalogService->validatePromo($product, $validated['code'])
        );
    }

    public function incrementPromoUse(Request $request, string $product): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
        ]);

        $check = $this->catalogService->validatePromo($product, $validated['code']);
        if (! ($check['valid'] ?? false)) {
            return response()->json($check, 422);
        }

        $this->catalogService->incrementPromoUse($validated['code']);

        return response()->json(['success' => true]);
    }
}
