<?php

namespace App\Http\Controllers;

use App\Services\ProductionDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request, ProductionDashboardService $dashboard): JsonResponse
    {
        $view = $request->query('view', ProductionDashboardService::VIEW_CONSOLIDATED);

        if (!in_array($view, [
            ProductionDashboardService::VIEW_A,
            ProductionDashboardService::VIEW_B,
            ProductionDashboardService::VIEW_CONSOLIDATED,
        ], true)) {
            return response()->json(['message' => 'Invalid view.'], 422);
        }

        return response()->json($dashboard->metrics($view));
    }
}
