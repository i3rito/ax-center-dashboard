<?php

namespace App\Http\Controllers;

use App\Services\ProductionDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ProductionDashboardService $dashboard): View
    {
        $view = $request->query('view', ProductionDashboardService::VIEW_CONSOLIDATED);

        if (!in_array($view, [
            ProductionDashboardService::VIEW_A,
            ProductionDashboardService::VIEW_B,
            ProductionDashboardService::VIEW_CONSOLIDATED,
        ], true)) {
            $view = ProductionDashboardService::VIEW_CONSOLIDATED;
        }

        return view('dashboard', [
            'initialView' => $view,
            'metrics' => $dashboard->metrics($view),
            'pusherKey' => config('broadcasting.connections.pusher.key'),
            'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
        ]);
    }
}
