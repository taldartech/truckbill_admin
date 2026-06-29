<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GstSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GstSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $setting = GstSetting::query()->orderByDesc('effective_from')->first();

        return response()->json([
            'gst' => $setting,
            'rate_percent' => $setting ? (float) $setting->rate_percent : 18.0,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $setting = GstSetting::create([
            'rate_percent' => $validated['rate_percent'],
            'effective_from' => $validated['effective_from'] ?? now()->toDateString(),
            'updated_by' => $request->user()?->id,
        ]);

        return response()->json(['gst' => $setting]);
    }
}
