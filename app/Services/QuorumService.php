<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Unidad;

class QuorumService
{
    public function calcular(Reunion $reunion): array
    {
        if ($reunion->tipo_voto_peso === 'coeficiente') {
            return $this->calcularPorCoeficiente($reunion);
        }

        return $this->calcularPorUnidad($reunion);
    }

    /**
     * Presencia SIEMPRE por coeficiente (Arts. 45/46 Ley 675 hablan de
     * coeficientes), independiente del tipo_voto_peso de la reunión.
     * Deduplica poderdantes que ya tienen asistencia propia.
     */
    public function presenciaCoeficiente(Reunion $reunion): array
    {
        $totalCoeficiente = (float) Unidad::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->sum('coeficiente');

        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        $coeficientePresente = (float) Unidad::withoutGlobalScopes()
            ->whereIn('copropietario_id', $presenteIds)
            ->sum('coeficiente');

        // Poderdantes representados que NO tienen asistencia propia
        // (los que sí la tienen ya se contaron arriba — evita doble conteo)
        $coeficienteDelegados = 0.0;
        if ($presenteIds->isNotEmpty()) {
            $poderdanteIds = Poder::withoutGlobalScopes()
                ->where('reunion_id', $reunion->id)
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->whereNotIn('poderdante_id', $presenteIds)
                ->pluck('poderdante_id');

            if ($poderdanteIds->isNotEmpty()) {
                $coeficienteDelegados = (float) Unidad::withoutGlobalScopes()
                    ->whereIn('copropietario_id', $poderdanteIds)
                    ->sum('coeficiente');
            }
        }

        $presente = $coeficientePresente + $coeficienteDelegados;

        return [
            'total'      => $totalCoeficiente,
            'presente'   => $presente,
            'porcentaje' => $totalCoeficiente > 0
                ? round(($presente / $totalCoeficiente) * 100, 2)
                : 0.0,
        ];
    }

    private function calcularPorCoeficiente(Reunion $reunion): array
    {
        $presencia = $this->presenciaCoeficiente($reunion);

        return [
            'tipo'                => 'coeficiente',
            'total'               => $presencia['total'],
            'presente'            => $presencia['presente'],
            'porcentaje_presente' => $presencia['porcentaje'],
            'quorum_requerido'    => (float) $reunion->quorum_requerido,
            'tiene_quorum'        => $presencia['porcentaje'] >= $reunion->quorum_requerido,
        ];
    }

    private function calcularPorUnidad(Reunion $reunion): array
    {
        // Total: copropietarios activos (no externos — los externos no tienen unidades)
        $totalUnidades = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->where('es_externo', false)
            ->count();

        // Asistentes directos
        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        $presentes = $presenteIds->count();

        // Poderdantes representados sin asistencia propia (evita doble conteo)
        $poderdantesRepresentados = 0;
        if ($presenteIds->isNotEmpty()) {
            $poderdantesRepresentados = Poder::withoutGlobalScopes()
                ->where('reunion_id', $reunion->id)
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->whereNotIn('poderdante_id', $presenteIds)
                ->count();
        }

        $totalPresente = $presentes + $poderdantesRepresentados;

        $porcentaje = $totalUnidades > 0
            ? round(($totalPresente / $totalUnidades) * 100, 2)
            : 0;

        return [
            'tipo'                => 'unidad',
            'total'               => $totalUnidades,
            'presente'            => $totalPresente,
            'porcentaje_presente' => $porcentaje,
            'quorum_requerido'    => (float) $reunion->quorum_requerido,
            'tiene_quorum'        => $porcentaje >= $reunion->quorum_requerido,
        ];
    }
}
