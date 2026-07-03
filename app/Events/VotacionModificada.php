<?php

namespace App\Events;

use App\Models\Votacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VotacionModificada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Votacion $votacion,
        public readonly string $accion,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->votacion->reunion_id}")];
    }

    public function broadcastWith(): array
    {
        $tipoDecision = $this->votacion->relationLoaded('tipoDecision')
            ? $this->votacion->tipoDecision
            : null;

        return [
            'votacion_id'  => $this->votacion->id,
            'reunion_id'   => $this->votacion->reunion_id,
            'pregunta'     => $this->votacion->pregunta,
            'descripcion'  => $this->votacion->descripcion,
            'opciones'     => $this->votacion->opciones->map(fn ($o) => [
                'id'    => $o->id,
                'texto' => $o->texto,
            ])->values()->all(),
            'estado'       => $this->votacion->estado,
            'accion'       => $this->accion,
            'tipo_decision' => $tipoDecision ? [
                'id'           => $tipoDecision->id,
                'nombre'       => $tipoDecision->nombre,
                'tipo_mayoria' => $tipoDecision->tipo_mayoria,
            ] : null,
        ];
    }
}
