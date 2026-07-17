<?php

namespace App\Http\Controllers;

use App\Services\OpenAIInsightService;
use App\Services\ProductionDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiInsightController extends Controller
{
    public function __invoke(
        Request $request,
        ProductionDashboardService $dashboard,
        OpenAIInsightService $openai
    ): JsonResponse {
        $view = $request->input('view', ProductionDashboardService::VIEW_CONSOLIDATED);

        if (!in_array($view, [
            ProductionDashboardService::VIEW_A,
            ProductionDashboardService::VIEW_B,
            ProductionDashboardService::VIEW_CONSOLIDATED,
        ], true)) {
            return response()->json(['message' => 'Invalid view.'], 422);
        }

        try {
            $metrics = $dashboard->metrics($view);
            $insight = $openai->summarize($metrics);

            return response()->json([
                'view' => $view,
                'insight' => $insight,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não foi possível gerar o insight.'], 500);
        }
    }
}
