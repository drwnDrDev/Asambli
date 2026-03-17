<?php
namespace App\Events;

use App\Models\Votacion;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultadosPublicosVotacion implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Votacion $votacion,
        public readonly array $resultados,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("reunion.{$this->votacion->reunion_id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'votacion_id' => $this->votacion->id,
            'resultados'  => $this->resultados,
        ];
    }
}
