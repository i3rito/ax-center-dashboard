<?php

namespace App\Services;

use App\Models\ProductionRecord;
use App\Support\PlantConnection;
use App\Support\ProductionMetrics;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionDashboardService
{
    public const VIEW_A = 'a';
    public const VIEW_B = 'b';
    public const VIEW_CONSOLIDATED = 'consolidated';

    public const REALTIME_DATE = '2026-02-01';
    public const TREND_START = '2026-01-01';

    public function metrics(string $view, ?string $date = null): array
    {
        $date = $date ?: self::REALTIME_DATE;
        $day = Carbon::parse($date)->startOfDay();

        switch ($view) {
            case self::VIEW_A:
                return $this->buildPayload(
                    self::VIEW_A,
                    PlantConnection::label(PlantConnection::A),
                    [$this->aggregateEloquent(PlantConnection::A, $day)],
                    [PlantConnection::A],
                    $day
                );

            case self::VIEW_B:
                return $this->buildPayload(
                    self::VIEW_B,
                    PlantConnection::label(PlantConnection::B),
                    [$this->aggregateSql(PlantConnection::B, $day)],
                    [PlantConnection::B],
                    $day
                );

            case self::VIEW_CONSOLIDATED:
                return $this->buildPayload(
                    self::VIEW_CONSOLIDATED,
                    'Consolidado (A + B)',
                    [
                        $this->aggregateEloquent(PlantConnection::A, $day),
                        $this->aggregateSql(PlantConnection::B, $day),
                    ],
                    PlantConnection::ALL,
                    $day
                );

            default:
                throw new InvalidArgumentException("Invalid dashboard view [{$view}].");
        }
    }

    private function buildPayload(
        string $view,
        string $label,
        array $plantBuckets,
        array $connections,
        Carbon $day
    ): array {
        $products = $this->mergeProductTotals($plantBuckets);
        $lines = $this->mergeLineTotals($plantBuckets);

        $productRows = $products->map(function (array $row) {
            return $this->withRates($row);
        })->values()->all();

        $lineRows = $lines->map(function (array $row) {
            return $this->withRates($row);
        })->values()->all();

        $totals = $this->withRates([
            'produced_qty' => (int) $products->sum('produced_qty'),
            'defective_qty' => (int) $products->sum('defective_qty'),
        ]);

        return [
            'view' => $view,
            'label' => $label,
            'date' => $day->toDateString(),
            'totals' => $totals,
            'products' => $productRows,
            'lines' => $lineRows,
            'trend' => $this->buildTrend($connections),
            'has_alerts' => collect($productRows)->contains('alert', true),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function buildTrend(array $connections): array
    {
        $start = Carbon::parse(self::TREND_START)->startOfDay();
        $end = Carbon::parse(self::REALTIME_DATE)->endOfDay();
        $merged = [];

        foreach ($connections as $connection) {
            $rows = $connection === PlantConnection::A
                ? $this->dailyTrendEloquent($connection, $start, $end)
                : $this->dailyTrendSql($connection, $start, $end);

            foreach ($rows as $row) {
                $key = $row['date'];
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'date' => $key,
                        'produced_qty' => 0,
                        'defective_qty' => 0,
                    ];
                }
                $merged[$key]['produced_qty'] += (int) $row['produced_qty'];
                $merged[$key]['defective_qty'] += (int) $row['defective_qty'];
            }
        }

        ksort($merged);

        return array_values(array_map(function (array $row) {
            return [
                'date' => $row['date'],
                'produced_qty' => (int) $row['produced_qty'],
                'defective_qty' => (int) $row['defective_qty'],
                'defect_rate' => ProductionMetrics::defectRate(
                    (int) $row['produced_qty'],
                    (int) $row['defective_qty']
                ),
            ];
        }, $merged));
    }

    private function dailyTrendEloquent(string $connection, Carbon $start, Carbon $end): array
    {
        return ProductionRecord::on($connection)
            ->whereBetween('recorded_at', [$start, $end])
            ->selectRaw('DATE(recorded_at) as date, SUM(produced_qty) as produced_qty, SUM(defective_qty) as defective_qty')
            ->groupBy(DB::raw('DATE(recorded_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => (string) $row->date,
                    'produced_qty' => (int) $row->produced_qty,
                    'defective_qty' => (int) $row->defective_qty,
                ];
            })
            ->all();
    }

    private function dailyTrendSql(string $connection, Carbon $start, Carbon $end): array
    {
        $rows = DB::connection($connection)->select(
            'SELECT DATE(recorded_at) AS date,
                    SUM(produced_qty) AS produced_qty,
                    SUM(defective_qty) AS defective_qty
             FROM production_records
             WHERE recorded_at BETWEEN ? AND ?
             GROUP BY DATE(recorded_at)
             ORDER BY date',
            [$start->toDateTimeString(), $end->toDateTimeString()]
        );

        return array_map(function ($row) {
            return [
                'date' => (string) $row->date,
                'produced_qty' => (int) $row->produced_qty,
                'defective_qty' => (int) $row->defective_qty,
            ];
        }, $rows);
    }

    private function withRates(array $row): array
    {
        $produced = (int) ($row['produced_qty'] ?? 0);
        $defective = (int) ($row['defective_qty'] ?? 0);
        $defectRate = ProductionMetrics::defectRate($produced, $defective);

        return array_merge($row, [
            'produced_qty' => $produced,
            'defective_qty' => $defective,
            'defect_rate' => $defectRate,
            'efficiency' => ProductionMetrics::efficiency($produced, $defective),
            'alert' => ProductionMetrics::isAlert($defectRate),
        ]);
    }

    private function mergeProductTotals(array $plantBuckets): Collection
    {
        $merged = [];

        foreach ($plantBuckets as $bucket) {
            foreach ($bucket['products'] as $row) {
                $key = $row['product_slug'];
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'product_id' => $row['product_id'],
                        'product_name' => $row['product_name'],
                        'product_slug' => $row['product_slug'],
                        'produced_qty' => 0,
                        'defective_qty' => 0,
                    ];
                }
                $merged[$key]['produced_qty'] += (int) $row['produced_qty'];
                $merged[$key]['defective_qty'] += (int) $row['defective_qty'];
            }
        }

        return collect($merged)->sortBy('product_name');
    }

    private function mergeLineTotals(array $plantBuckets): Collection
    {
        $merged = [];

        foreach ($plantBuckets as $bucket) {
            foreach ($bucket['lines'] as $row) {
                $key = $row['line_code'];
                if (!isset($merged[$key])) {
                    $merged[$key] = [
                        'line_name' => $row['line_name'],
                        'line_code' => $row['line_code'],
                        'produced_qty' => 0,
                        'defective_qty' => 0,
                    ];
                }
                $merged[$key]['produced_qty'] += (int) $row['produced_qty'];
                $merged[$key]['defective_qty'] += (int) $row['defective_qty'];
            }
        }

        return collect($merged)->sortBy('line_code');
    }

    private function aggregateEloquent(string $connection, Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $products = ProductionRecord::on($connection)
            ->from('production_records as pr')
            ->join('products as p', 'p.id', '=', 'pr.product_id')
            ->whereBetween('pr.recorded_at', [$start, $end])
            ->groupBy('p.id', 'p.name', 'p.slug')
            ->orderBy('p.name')
            ->get([
                'p.id as product_id',
                'p.name as product_name',
                'p.slug as product_slug',
                DB::raw('SUM(pr.produced_qty) as produced_qty'),
                DB::raw('SUM(pr.defective_qty) as defective_qty'),
            ])
            ->map(function ($row) {
                return [
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product_name,
                    'product_slug' => $row->product_slug,
                    'produced_qty' => (int) $row->produced_qty,
                    'defective_qty' => (int) $row->defective_qty,
                ];
            })
            ->all();

        $lines = ProductionRecord::on($connection)
            ->from('production_records as pr')
            ->join('production_lines as pl', 'pl.id', '=', 'pr.production_line_id')
            ->whereBetween('pr.recorded_at', [$start, $end])
            ->groupBy('pl.id', 'pl.name', 'pl.code')
            ->orderBy('pl.code')
            ->get([
                'pl.name as line_name',
                'pl.code as line_code',
                DB::raw('SUM(pr.produced_qty) as produced_qty'),
                DB::raw('SUM(pr.defective_qty) as defective_qty'),
            ])
            ->map(function ($row) {
                return [
                    'line_name' => $row->line_name,
                    'line_code' => $row->line_code,
                    'produced_qty' => (int) $row->produced_qty,
                    'defective_qty' => (int) $row->defective_qty,
                ];
            })
            ->all();

        return compact('products', 'lines');
    }

    private function aggregateSql(string $connection, Carbon $day): array
    {
        $start = $day->copy()->startOfDay()->toDateTimeString();
        $end = $day->copy()->endOfDay()->toDateTimeString();

        $products = DB::connection($connection)->select(
            'SELECT p.id AS product_id, p.name AS product_name, p.slug AS product_slug,
                    COALESCE(SUM(pr.produced_qty), 0) AS produced_qty,
                    COALESCE(SUM(pr.defective_qty), 0) AS defective_qty
             FROM products p
             LEFT JOIN production_records pr
               ON pr.product_id = p.id
              AND pr.recorded_at BETWEEN ? AND ?
             GROUP BY p.id, p.name, p.slug
             ORDER BY p.name',
            [$start, $end]
        );

        $lines = DB::connection($connection)->select(
            'SELECT pl.name AS line_name, pl.code AS line_code,
                    COALESCE(SUM(pr.produced_qty), 0) AS produced_qty,
                    COALESCE(SUM(pr.defective_qty), 0) AS defective_qty
             FROM production_lines pl
             LEFT JOIN production_records pr
               ON pr.production_line_id = pl.id
              AND pr.recorded_at BETWEEN ? AND ?
             GROUP BY pl.id, pl.name, pl.code
             ORDER BY pl.code',
            [$start, $end]
        );

        return [
            'products' => array_map(function ($row) {
                return (array) $row;
            }, $products),
            'lines' => array_map(function ($row) {
                return (array) $row;
            }, $lines),
        ];
    }
}
