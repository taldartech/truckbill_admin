<?php

namespace App\Support;

use App\Models\Lead;

class CrmDashboardStats
{
    /**
     * @return array{
     *     total: int,
     *     new: int,
     *     contacted: int,
     *     qualified: int,
     *     demo_scheduled: int,
     *     proposal_sent: int,
     *     converted: int,
     *     lost: int,
     *     by_product: array<string, int>,
     *     by_source: array<string, int>
     * }
     */
    public static function build(): array
    {
        $byStatus = Lead::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byProduct = Lead::query()
            ->selectRaw('product, count(*) as total')
            ->whereNotNull('product')
            ->groupBy('product')
            ->pluck('total', 'product');

        $bySource = Lead::query()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        return [
            'total' => Lead::count(),
            'new' => (int) ($byStatus['new'] ?? 0),
            'contacted' => (int) ($byStatus['contacted'] ?? 0),
            'qualified' => (int) ($byStatus['qualified'] ?? 0),
            'demo_scheduled' => (int) ($byStatus['demo_scheduled'] ?? 0),
            'proposal_sent' => (int) ($byStatus['proposal_sent'] ?? 0),
            'converted' => (int) ($byStatus['converted'] ?? 0),
            'lost' => (int) ($byStatus['lost'] ?? 0),
            'by_product' => $byProduct->mapWithKeys(fn ($total, $product) => [(string) $product => (int) $total])->all(),
            'by_source' => $bySource->mapWithKeys(fn ($total, $source) => [(string) $source => (int) $total])->all(),
        ];
    }
}
