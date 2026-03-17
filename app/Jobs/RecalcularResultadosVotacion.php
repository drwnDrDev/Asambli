<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalcularResultadosVotacion implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $votacionId,
        public ?int $copropietarioId = null
    ) {}

    public function handle(): void
    {
        $votacion = \App\Models\Votacion::with('opciones')->withoutGlobalScopes()->find($this->votacionId);

        if (!$votacion) return;

        $resultados = $votacion->opciones->map(function ($opcion) use ($votacion) {
            $votos = \App\Models\Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id);

            return [
                'opcion_id' => $opcion->id,
                'texto' => $opcion->texto,
                'count' => $votos->count(),
                'peso_total' => (float) $votos->sum('peso'),
            ];
        });

        $ultimoVotoUnidad = null;
        if ($this->copropietarioId) {
            $copropietario = \App\Models\Copropietario::withoutGlobalScopes()
                ->with('unidades')
                ->find($this->copropietarioId);
            $ultimoVotoUnidad = $copropietario?->unidades->first()?->numero;
        }

        broadcast(new \App\Events\ResultadosVotacionActualizados($votacion, $resultados->toArray(), $ultimoVotoUnidad));
        broadcast(new \App\Events\ResultadosPublicosVotacion($votacion, $resultados->toArray()));
    }
}
