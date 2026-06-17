<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class LeadActivityPresenter
{
    /** @var list<string> */
    private const LEGACY_AUTO_DESCRIPTIONS = ['created', 'updated', 'deleted'];

    public static function collection(Collection $activities): array
    {
        return $activities
            ->reject(fn (Activity $activity) => in_array($activity->description, self::LEGACY_AUTO_DESCRIPTIONS, true))
            ->map(fn (Activity $activity) => self::present($activity))
            ->values()
            ->all();
    }

    public static function present(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            'created_at' => $activity->created_at?->toIso8601String(),
            'actor' => $activity->causer?->name,
            'properties' => $activity->properties?->toArray() ?? [],
        ];
    }
}
