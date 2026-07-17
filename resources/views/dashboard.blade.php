<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AX Center · Dashboard de Produção</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
</head>
<body>
    <div class="shell" id="dashboard"
         data-view="{{ $initialView }}"
         data-pusher-key="{{ $pusherKey }}"
         data-pusher-cluster="{{ $pusherCluster }}">
        <header class="top">
            <div>
                <p class="eyebrow">LG Electronics · AX Center</p>
                <h1>Dashboard Centralizado de Produção</h1>
            </div>
            <div class="meta">
                <span class="chip" id="live-date">{{ $metrics['date'] }}</span>
                <span class="chip muted" id="updated-at">Atualizado {{ $metrics['updated_at'] }}</span>
            </div>
        </header>

        <div class="toolbar">
            <nav class="views" aria-label="Modo de visualização">
                <button type="button" data-view-btn="a" class="{{ $initialView === 'a' ? 'is-active' : '' }}">Planta A</button>
                <button type="button" data-view-btn="b" class="{{ $initialView === 'b' ? 'is-active' : '' }}">Planta B</button>
                <button type="button" data-view-btn="consolidated" class="{{ $initialView === 'consolidated' ? 'is-active' : '' }}">Consolidado</button>
            </nav>
            <button type="button" id="insight-btn" class="btn btn-insight">Gerar insight</button>
        </div>

        <section class="alert-banner {{ $metrics['has_alerts'] ? 'is-visible' : '' }}" id="alert-banner">
            Taxa de defeitos acima de 5% detectada em um ou mais produtos.
        </section>

        <section class="kpis" id="kpi-grid">
            <article class="kpi">
                <span>Produzido</span>
                <strong id="kpi-produced">{{ number_format($metrics['totals']['produced_qty'], 0, ',', '.') }}</strong>
            </article>
            <article class="kpi">
                <span>Defeituosos</span>
                <strong id="kpi-defective">{{ number_format($metrics['totals']['defective_qty'], 0, ',', '.') }}</strong>
            </article>
            <article class="kpi {{ $metrics['totals']['alert'] ? 'is-alert' : '' }}" id="kpi-defect-card">
                <span>Taxa de defeitos</span>
                <strong id="kpi-defect-rate">{{ number_format($metrics['totals']['defect_rate'], 2, ',', '.') }}%</strong>
            </article>
            <article class="kpi">
                <span>Eficiência</span>
                <strong id="kpi-efficiency">{{ number_format($metrics['totals']['efficiency'], 2, ',', '.') }}%</strong>
            </article>
        </section>

        <section class="charts-grid">
            <div class="panel">
                <div class="panel-head">
                    <h2>Produção por produto</h2>
                </div>
                <canvas id="chart-production" height="220"></canvas>
            </div>
            <div class="panel">
                <div class="panel-head">
                    <h2>Produzido × defeituosos</h2>
                </div>
                <canvas id="chart-volume" height="220"></canvas>
            </div>
            <div class="panel">
                <div class="panel-head">
                    <h2>Taxa de defeitos por produto</h2>
                </div>
                <canvas id="chart-defect-rate" height="220"></canvas>
            </div>
            <div class="panel">
                <div class="panel-head">
                    <h2>Tendência diária (jan/2026 + 01/02)</h2>
                </div>
                <canvas id="chart-trend" height="220"></canvas>
            </div>
        </section>

        <section class="grid grid-table">
            <div class="panel panel-wide">
                <div class="panel-head">
                    <h2 id="view-label">{{ $metrics['label'] }} · por produto</h2>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Produzido</th>
                                <th>Defeituosos</th>
                                <th>Taxa</th>
                                <th>Eficiência</th>
                            </tr>
                        </thead>
                        <tbody id="products-body">
                            @foreach ($metrics['products'] as $row)
                                <tr class="{{ $row['alert'] ? 'is-alert' : '' }}">
                                    <td>{{ $row['product_name'] }}</td>
                                    <td>{{ number_format($row['produced_qty'], 0, ',', '.') }}</td>
                                    <td>{{ number_format($row['defective_qty'], 0, ',', '.') }}</td>
                                    <td>{{ number_format($row['defect_rate'], 2, ',', '.') }}%</td>
                                    <td>{{ number_format($row['efficiency'], 2, ',', '.') }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="modal" id="insight-modal" hidden>
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="insight-modal-title">
            <div class="modal-head">
                <h2 id="insight-modal-title">Insight (OpenAI)</h2>
                <button type="button" class="modal-close" data-close-modal aria-label="Fechar">&times;</button>
            </div>
            <pre id="insight-output" class="insight">Gerando insight...</pre>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://js.pusher.com/7.2/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
    <script>
        window.AX_INITIAL_METRICS = @json($metrics);
        document.addEventListener('DOMContentLoaded', function () {
            window.AXDashboard.init(document.getElementById('dashboard'), window.AX_INITIAL_METRICS);
        });
    </script>
</body>
</html>
