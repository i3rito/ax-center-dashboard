<?php

namespace Tests\Feature;

use App\Services\OpenAIInsightService;
use App\Services\ProductionDashboardService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    public function test_metrics_endpoint_returns_consolidated_payload()
    {
        $response = $this->getJson('/api/metrics?view=consolidated');

        $response->assertOk()
            ->assertJsonPath('view', 'consolidated')
            ->assertJsonStructure([
                'view',
                'label',
                'date',
                'totals' => ['produced_qty', 'defective_qty', 'defect_rate', 'efficiency', 'alert'],
                'products',
                'lines',
                'trend' => [
                    '*' => ['date', 'produced_qty', 'defective_qty', 'defect_rate'],
                ],
                'has_alerts',
                'updated_at',
            ]);

        $this->assertNotEmpty($response->json('products'));
        $this->assertNotEmpty($response->json('trend'));
        $this->assertSame('2026-01-01', $response->json('trend.0.date'));
        $this->assertContains('2026-02-01', collect($response->json('trend'))->pluck('date')->all());
    }

    public function test_metrics_endpoint_rejects_invalid_view()
    {
        $this->getJson('/api/metrics?view=xyz')->assertStatus(422);
    }

    public function test_insight_endpoint_uses_openai_service()
    {
        config(['services.openai.key' => 'test-key']);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'choices' => [
                    ['message' => ['content' => '- Risco em TV na Planta A.']],
                ],
            ])),
        ]);

        $client = new Client(['handler' => HandlerStack::create($mock)]);
        $this->app->instance(
            OpenAIInsightService::class,
            (new OpenAIInsightService())->usingClient($client)
        );

        $response = $this->postJson('/api/insights', [
            'view' => ProductionDashboardService::VIEW_A,
        ]);

        $response->assertOk()->assertJsonPath('insight', '- Risco em TV na Planta A.');
    }
}
