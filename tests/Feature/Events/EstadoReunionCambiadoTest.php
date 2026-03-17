<?php
use App\Events\EstadoReunionCambiado;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the public reunion channel', function () {
    $reunion = \App\Models\Reunion::factory()->create();

    $event = new EstadoReunionCambiado($reunion, 'en_curso');

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe("reunion.{$reunion->id}");
});

it('broadcasts estado and timestamp', function () {
    $reunion = \App\Models\Reunion::factory()->create();

    $event = new EstadoReunionCambiado($reunion, 'suspendida');
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('estado', 'suspendida')
        ->and($payload)->toHaveKey('timestamp');
});
