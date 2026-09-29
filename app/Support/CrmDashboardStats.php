<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\User;

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
     *     trial: int,
     *     converted: int,
     *     lost: int,
     *     follow_up: array{overdue: int, due_today: int, upcoming: int},
     *     by_product: array<string, int>,
     *     by_source: array<string, int>
     * }
     */
    public static function build(?User $user = null): array
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

        $assignedLeads = $user !== null
            ? Lead::query()->where('assigned_to', $user->id)
            : null;

        return [
            'total' => Lead::count(),
            'new' => (int) ($byStatus['new'] ?? 0),
            'contacted' => (int) ($byStatus['contacted'] ?? 0),
            'qualified' => (int) ($byStatus['qualified'] ?? 0),
            'demo_scheduled' => (int) ($byStatus['demo_scheduled'] ?? 0),
            'proposal_sent' => (int) ($byStatus['proposal_sent'] ?? 0),
            'trial' => (int) ($byStatus['trial'] ?? 0),
            'converted' => (int) ($byStatus['converted'] ?? 0),
            'lost' => (int) ($byStatus['lost'] ?? 0),
            'follow_up' => [
                'overdue' => $assignedLeads !== null
                    ? (clone $assignedLeads)->followUpFilter('overdue')->count()
                    : 0,
                'due_today' => $assignedLeads !== null
                    ? (clone $assignedLeads)->followUpFilter('due_today')->count()
                    : 0,
                'upcoming' => $assignedLeads !== null
                    ? (clone $assignedLeads)->followUpFilter('upcoming')->count()
                    : 0,
            ],
            'by_product' => $byProduct->mapWithKeys(fn ($total, $product) => [(string) $product => (int) $total])->all(),
            'by_source' => $bySource->mapWithKeys(fn ($total, $source) => [(string) $source => (int) $total])->all(),
        ];
    }
}
