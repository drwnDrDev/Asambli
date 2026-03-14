<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvisoEnviado implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public int $reunionId,
        public string $mensaje,
        public string $enviadoAt
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->reunionId}")];
    }

    public function broadcastWith(): array
    {
        return [
            'mensaje'     => $this->mensaje,
            'enviado_at'  => $this->enviadoAt,
        ];
    }
}
