<?php
use App\Events\ResultadosPublicosVotacion;
use Illuminate\Broadcasting\Channel;

it('broadcasts on the public reunion channel', function () {
    $votacion = \App\Models\Votacion::factory()->create();

    $event = new ResultadosPublicosVotacion($votacion, [
        ['opcion_id' => 1, 'texto' => 'SÍ', 'count' => 5, 'peso_total' => 0.5],
    ]);

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0])->toBeInstanceOf(Channel::class)
        ->and($channels[0]->name)->toBe("reunion.{$votacion->reunion_id}");
});

it('broadcasts with reduced payload without ultimo_voto_unidad', function () {
    $votacion = \App\Models\Votacion::factory()->create();
    $resultados = [
        ['opcion_id' => 1, 'texto' => 'SÍ', 'count' => 5, 'peso_total' => 0.5],
        ['opcion_id' => 2, 'texto' => 'NO', 'count' => 3, 'peso_total' => 0.3],
    ];

    $event = new ResultadosPublicosVotacion($votacion, $resultados);
    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('votacion_id', $votacion->id)
        ->and($payload)->toHaveKey('resultados')
        ->and($payload)->not->toHaveKey('ultimo_voto_unidad');
});
