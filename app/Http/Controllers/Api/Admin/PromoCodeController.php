<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Product;
use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'promo_codes' => PromoCode::query()->orderByDesc('created_at')->get(),
            'products' => collect(Product::cases())->map(fn (Product $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'discount_types' => [
                ['value' => 'percentage', 'label' => 'Percentage'],
                ['value' => 'absolute', 'label' => 'Absolute (₹)'],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePromo($request);

        $promo = PromoCode::create(array_merge($validated, [
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()?->id,
        ]));

        return response()->json(['promo_code' => $promo], 201);
    }

    public function update(Request $request, PromoCode $promoCode): JsonResponse
    {
        $validated = $this->validatePromo($request, $promoCode->id);

        $promoCode->update(array_merge($validated, [
            'code' => strtoupper($validated['code']),
        ]));

        return response()->json(['promo_code' => $promoCode->fresh()]);
    }

    public function destroy(PromoCode $promoCode): JsonResponse
    {
        $promoCode->delete();

        return response()->json(['success' => true]);
    }

    private function validatePromo(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('promo_codes', 'code')->ignore($ignoreId),
            ],
            'discount_type' => ['required', Rule::in(['percentage', 'absolute'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'applicable_products' => ['required', 'array', 'min:1'],
            'applicable_products.*' => [Rule::enum(Product::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
