<?php
namespace App\Events;

use App\Models\Reunion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EstadoReunionCambiado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Reunion $reunion,
        public readonly string $estado,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("reunion.{$this->reunion->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'estado'    => $this->estado,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
