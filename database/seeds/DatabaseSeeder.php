<?php

use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\ProductionRecord;
use App\Support\ProductionMetrics;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    private const PRODUCTS = [
        ['name' => 'Geladeira', 'slug' => 'geladeira'],
        ['name' => 'Monitor', 'slug' => 'monitor'],
        ['name' => 'Máquina de Lavar', 'slug' => 'maquina-de-lavar'],
        ['name' => 'TV', 'slug' => 'tv'],
        ['name' => 'Ar-Condicionado', 'slug' => 'ar-condicionado'],
    ];

    private const LINES = [
        ['name' => 'Planta A', 'code' => 'L1'],
        ['name' => 'Planta B', 'code' => 'L2'],
    ];

    public function run()
    {
        $connection = DB::getDefaultConnection();
        $seed = $connection === 'planta_b' ? 42 : 7;
        mt_srand($seed);

        DB::connection($connection)->transaction(function () use ($connection, $seed) {
            ProductionRecord::on($connection)->delete();
            ProductionLine::on($connection)->delete();
            Product::on($connection)->delete();

            foreach (self::PRODUCTS as $product) {
                Product::on($connection)->create($product);
            }

            foreach (self::LINES as $line) {
                ProductionLine::on($connection)->create($line);
            }

            $products = Product::on($connection)->get();
            $lines = ProductionLine::on($connection)->get();

            $this->seedHistory($connection, $products, $lines, $seed);
            $this->seedRealtimeDay($connection, $products, $lines, $seed);
        });
    }

    private function seedHistory($connection, $products, $lines, int $seed): void
    {
        $period = CarbonPeriod::create('2026-01-01', '2026-01-31');
        $rows = [];

        foreach ($period as $day) {
            foreach ($products as $index => $product) {
                $line = $lines[$index % $lines->count()];
                $produced = 80 + (($seed + $day->day + $index * 3) % 70);
                $defectRate = 1.5 + (($seed + $index + $day->day) % 70) / 10;
                if ($product->slug === 'monitor' && $day->day > 20) {
                    $defectRate = ProductionMetrics::DEFECT_ALERT_THRESHOLD + 1.2 + ($seed % 3);
                }

                $defective = (int) round($produced * ($defectRate / 100));

                $rows[] = [
                    'product_id' => $product->id,
                    'production_line_id' => $line->id,
                    'produced_qty' => $produced,
                    'defective_qty' => $defective,
                    'recorded_at' => $day->copy()->setTime(10 + ($index % 6), 0, 0)->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        ProductionRecord::on($connection)->insert($rows);
    }

    private function seedRealtimeDay($connection, $products, $lines, int $seed): void
    {
        $day = Carbon::parse('2026-02-01');
        $rows = [];

        foreach ($products as $index => $product) {
            $line = $lines[$index % $lines->count()];
            $produced = 40 + (($seed + $index * 11) % 40);
            $defectRate = 2 + (($seed + $index * 5) % 40) / 10;

            if ($product->slug === 'tv' && $connection === 'planta_a') {
                $defectRate = 6.4;
            }
            if ($product->slug === 'ar-condicionado' && $connection === 'planta_b') {
                $defectRate = 7.1;
            }

            $defective = (int) round($produced * ($defectRate / 100));

            $rows[] = [
                'product_id' => $product->id,
                'production_line_id' => $line->id,
                'produced_qty' => $produced,
                'defective_qty' => max(1, $defective),
                'recorded_at' => $day->copy()->setTime(8 + $index, 15, 0)->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProductionRecord::on($connection)->insert($rows);
    }
}
