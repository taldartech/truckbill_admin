<?php

namespace App\Services;

use App\Models\SubscriptionSetting;

class SubscriptionSettingsService
{
    /** @var array<string, mixed> */
    private const DEFAULTS = [
        'grace_period_days' => 15,
        'gst_rate_percent' => 18.0,
    ];

    /** @return array<string, mixed> */
    public function currentSettings(): array
    {
        $row = SubscriptionSetting::query()->orderByDesc('effective_from')->orderBy('id', 'desc')->first();

        if ($row === null) {
            return self::DEFAULTS;
        }

        return array_merge(self::DEFAULTS, $row->settings ?? []);
    }

    public function gracePeriodDays(): int
    {
        return (int) $this->currentSettings()['grace_period_days'];
    }

    public function gstRatePercent(): float
    {
        return (float) $this->currentSettings()['gst_rate_percent'];
    }

    /** @return array{effective_from: string|null, updated_by: int|null} */
    public function currentMeta(): array
    {
        $row = SubscriptionSetting::query()->orderByDesc('effective_from')->orderBy('id', 'desc')->first();

        return [
            'effective_from' => $row?->effective_from?->toDateString(),
            'updated_by' => $row?->updated_by,
        ];
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function store(array $settings, ?int $updatedBy = null): SubscriptionSetting
    {
        $current = $this->currentSettings();

        return SubscriptionSetting::create([
            'settings' => array_merge($current, $settings),
            'effective_from' => now()->toDateString(),
            'updated_by' => $updatedBy,
        ]);
    }
}
