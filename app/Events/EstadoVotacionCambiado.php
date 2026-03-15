<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EstadoVotacionCambiado implements ShouldBroadcastNow
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
            'estado'      => $this->votacion->estado,
            'pregunta'    => $this->votacion->pregunta,
            'opciones'    => $this->votacion->opciones->map(fn($o) => [
                'id'    => $o->id,
                'texto' => $o->texto,
            ])->values()->all(),
        ];
    }
}
