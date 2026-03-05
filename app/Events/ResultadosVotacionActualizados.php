<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultadosVotacionActualizados implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly \App\Models\Votacion $votacion,
        public readonly array $resultados
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('reunion.' . $this->votacion->reunion_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id' => $this->votacion->id,
            'resultados' => $this->resultados,
        ];
    }
}
