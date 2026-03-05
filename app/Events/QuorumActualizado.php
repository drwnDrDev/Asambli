<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuorumActualizado implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public readonly int $reunionId,
        public readonly array $quorumData
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel("reunion.{$this->reunionId}")];
    }
}
