<?php

namespace App\Console\Commands;

use App\Events\ProductionUpdated;
use App\Models\Product;
use App\Models\ProductionLine;
use App\Models\ProductionRecord;
use App\Services\ProductionDashboardService;
use App\Support\PlantConnection;
use Carbon\Carbon;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Console\Command;

class SimulateProduction extends Command
{
    protected $signature = 'production:simulate {--plant= : planta_a, planta_b or both}';

    protected $description = 'Insert a random production record for the realtime day and broadcast update';

    public function handle(): int
    {
        $plantOption = $this->option('plant') ?: 'both';
        $plants = $plantOption === 'both'
            ? PlantConnection::ALL
            : [$plantOption];

        $date = Carbon::parse(ProductionDashboardService::REALTIME_DATE);

        foreach ($plants as $connection) {
            if (!in_array($connection, PlantConnection::ALL, true)) {
                $this->error("Unknown plant [{$connection}]");
                return 1;
            }

            $this->tick($connection, $date);

            try {
                event(new ProductionUpdated($connection, $date->toDateString()));
            } catch (BroadcastException $e) {
                $this->warn("Broadcast skipped on {$connection}: configure PUSHER_* in .env");
            }

            $this->info("Simulated tick on {$connection}");
        }

        return 0;
    }

    private function tick(string $connection, Carbon $date): void
    {
        $product = Product::on($connection)->inRandomOrder()->firstOrFail();
        $line = ProductionLine::on($connection)->inRandomOrder()->firstOrFail();

        $produced = random_int(8, 25);
        $defective = random_int(0, max(1, (int) ceil($produced * 0.12)));

        ProductionRecord::on($connection)->create([
            'product_id' => $product->id,
            'production_line_id' => $line->id,
            'produced_qty' => $produced,
            'defective_qty' => $defective,
            'recorded_at' => $date->copy()->setTime(
                random_int(8, 17),
                random_int(0, 59),
                random_int(0, 59)
            ),
        ]);
    }
}
