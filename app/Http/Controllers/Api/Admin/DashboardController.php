<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function crm(): JsonResponse
    {
        $stats = Cache::remember('crm.dashboard.stats', 300, function () {
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
                'by_product' => $byProduct,
                'by_source' => $bySource,
            ];
        });

        return response()->json($stats);
    }
}
