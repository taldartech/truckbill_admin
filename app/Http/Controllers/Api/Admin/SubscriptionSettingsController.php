<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionSettingsController extends Controller
{
    public function __construct(
        private readonly SubscriptionSettingsService $settingsService,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsService->currentSettings(),
            'meta' => $this->settingsService->currentMeta(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'grace_period_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'gst_rate_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $setting = $this->settingsService->store($validated, $request->user()?->id);

        return response()->json([
            'settings' => $setting->settings,
            'meta' => [
                'effective_from' => $setting->effective_from->toDateString(),
                'updated_by' => $setting->updated_by,
            ],
        ]);
    }
}
