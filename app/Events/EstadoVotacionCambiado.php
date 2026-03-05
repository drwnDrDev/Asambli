<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EstadoVotacionCambiado implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public readonly \App\Models\Votacion $votacion) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->votacion->reunion_id}")];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id' => $this->votacion->id,
            'estado' => $this->votacion->estado,
            'titulo' => $this->votacion->titulo,
        ];
    }
}
