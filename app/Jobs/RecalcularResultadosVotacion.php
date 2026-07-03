<?php

namespace App\Jobs;

use App\Models\Unidad;
use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalcularResultadosVotacion implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Votacion $votacion,
        public ?int $copropietarioId = null
    ) {}

    public function handle(): void
    {
        $votacion = $this->votacion->loadMissing('opciones', 'tipoDecision');

        $resultados = $votacion->opciones->map(function ($opcion) use ($votacion) {
            $votos = Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcion->id);

            return [
                'opcion_id'  => $opcion->id,
                'texto'      => $opcion->texto,
                'count'      => $votos->count(),
                'peso_total' => (float) $votos->sum('peso'),
            ];
        });

        $ultimoVotoUnidad = null;
        if ($this->copropietarioId) {
            $copropietario = \App\Models\Copropietario::withoutGlobalScopes()
                ->with('unidades')
                ->find($this->copropietarioId);
            $numeros = $copropietario?->unidades->pluck('numero')->filter()->values();
            $ultimoVotoUnidad = $numeros?->isNotEmpty() ? $numeros->join(', ') : null;
        }

        $mayoriaData = $this->calcularMayoriaData($votacion);

        broadcast(new \App\Events\ResultadosVotacionActualizados($votacion, $resultados->toArray(), $ultimoVotoUnidad, $mayoriaData));
        broadcast(new \App\Events\ResultadosPublicosVotacion($votacion, $resultados->toArray()));
    }

    private function calcularMayoriaData(Votacion $votacion): array
    {
        $tipoDecision = $votacion->tipoDecision;
        $tipoMayoria  = $tipoDecision?->tipo_mayoria ?? 'simple';

        // Opción de favor: orden = 1
        $opcionFavor = $votacion->opciones->firstWhere('orden', 1);
        $votosFavor  = 0.0;

        if ($opcionFavor) {
            $votosFavor = (float) Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacion->id)
                ->where('opcion_id', $opcionFavor->id)
                ->sum('peso');
        }

        // Total emitido (todos los votos de esta votación)
        $totalEmitido = (float) Voto::withoutGlobalScopes()
            ->where('votacion_id', $votacion->id)
            ->sum('peso');

        $votosEnContra = $totalEmitido - $votosFavor;

        switch ($tipoMayoria) {
            case 'calificada_70':
                $totalEdificio = (float) Unidad::withoutGlobalScopes()
                    ->where('tenant_id', $votacion->tenant_id)
                    ->sum('coeficiente');

                $porcentajeSobreEdificio = $totalEdificio > 0
                    ? round($votosFavor / $totalEdificio * 100, 10)
                    : 0.0;

                return [
                    'tipo_mayoria'              => 'calificada_70',
                    'votos_favor'               => $votosFavor,
                    'total_edificio'            => $totalEdificio,
                    'porcentaje_sobre_edificio' => $porcentajeSobreEdificio,
                    'umbral'                    => 70.0,
                    'resultado_tentativo'       => $porcentajeSobreEdificio >= 70.0 ? 'aprobada' : 'rechazada',
                ];

            case 'unanimidad':
                $aprobada = $totalEmitido > 0 && $votosEnContra == 0.0;

                return [
                    'tipo_mayoria'        => 'unanimidad',
                    'votos_favor'         => $votosFavor,
                    'votos_en_contra'     => $votosEnContra,
                    'total_emitido'       => $totalEmitido,
                    'resultado_tentativo' => $aprobada ? 'aprobada' : 'rechazada',
                ];

            default: // 'simple'
                $porcentajeFavor = $totalEmitido > 0
                    ? round($votosFavor / $totalEmitido * 100, 10)
                    : 0.0;

                return [
                    'tipo_mayoria'        => 'simple',
                    'votos_favor'         => $votosFavor,
                    'votos_en_contra'     => $votosEnContra,
                    'total_emitido'       => $totalEmitido,
                    'porcentaje_favor'    => $porcentajeFavor,
                    'umbral'              => 50.0,
                    'resultado_tentativo' => $porcentajeFavor > 50.0 ? 'aprobada' : 'rechazada',
                ];
        }
    }
}
