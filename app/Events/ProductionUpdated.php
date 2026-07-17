<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $plant;
    public string $date;

    public function __construct(string $plant, string $date)
    {
        $this->plant = $plant;
        $this->date = $date;
    }

    public function broadcastOn()
    {
        return new Channel('production');
    }

    public function broadcastAs()
    {
        return 'updated';
    }
}
