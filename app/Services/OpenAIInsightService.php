<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class OpenAIInsightService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'timeout' => 30,
        ]);
    }

    public function usingClient(Client $http): self
    {
        $this->http = $http;

        return $this;
    }

    public function summarize(array $metrics): string
    {
        $apiKey = config('services.openai.key');

        if (!$apiKey) {
            throw new RuntimeException('OPENAI_API_KEY não configurada.');
        }

        $payload = [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.4,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Você é um analista de produção industrial da LG. Responda em português do Brasil, em 3 a 5 bullets objetivos, sem markdown excessivo.',
                ],
                [
                    'role' => 'user',
                    'content' => "Com base nestes indicadores do dashboard (JSON), aponte riscos de defeito (>5%), compare plantas quando houver dados e sugira uma ação:\n"
                        . json_encode($metrics, JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];

        try {
            $response = $this->http->post('chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Falha ao consultar a OpenAI: ' . $e->getMessage(), 0, $e);
        }

        $body = json_decode((string) $response->getBody(), true);
        $content = $body['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new RuntimeException('Resposta vazia da OpenAI.');
        }

        return trim($content);
    }
}
