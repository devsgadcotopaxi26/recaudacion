<?php

namespace App\Http\Controllers;

use App\Models\SriRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SriMonitoringController extends Controller
{
    /**
     * Obtener métricas del SRI
     */
    public function metrics(Request $request)
    {
        $hours = $request->get('hours', 24);

        $metrics = [
            'summary' => $this->getSummary($hours),
            'by_endpoint' => $this->getByEndpoint($hours),
            'errors' => $this->getRecentErrors(10),
            'performance' => $this->getPerformanceByHour($hours),
        ];

        return response()->json($metrics);
    }

    /**
     * Resumen general
     */
    private function getSummary(int $hours)
    {
        $requests = SriRequest::lastHours($hours)->get();
        $total = $requests->count();
        $successful = $requests->where('success', true)->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0,
            'avg_duration' => round($requests->avg('duration_ms') ?? 0),
            'from_cache' => $requests->where('cached', true)->count(),
            'cache_rate' => $total > 0 ? round(($requests->where('cached', true)->count() / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Métricas por endpoint
     */
    private function getByEndpoint(int $hours)
    {
        return SriRequest::lastHours($hours)
            ->select(
                'endpoint',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('MAX(duration_ms) as max_duration')
            )
            ->groupBy('endpoint')
            ->get()
            ->map(function ($item) {
                $item->avg_duration = round($item->avg_duration);
                $item->success_rate = $item->total > 0 ? round(($item->successful / $item->total) * 100, 2) : 0;
                return $item;
            });
    }

    /**
     * Errores recientes
     */
    private function getRecentErrors(int $limit = 10)
    {
        return SriRequest::failed()
            ->latest()
            ->limit($limit)
            ->get(['id', 'placa', 'endpoint', 'error_type', 'error_message', 'created_at']);
    }

    /**
     * Performance por hora
     */
    private function getPerformanceByHour(int $hours)
    {
        return SriRequest::lastHours($hours)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00") as hour'),
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(duration_ms) as avg_duration'),
                DB::raw('SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                $item->avg_duration = round($item->avg_duration);
                $item->success_rate = $item->total > 0 ? round(($item->successful / $item->total) * 100, 2) : 0;
                return $item;
            });
    }
}
