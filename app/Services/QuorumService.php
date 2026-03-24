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

    private function calcularPorCoeficiente(Reunion $reunion): array
    {
        $totalCoeficiente = Unidad::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->sum('coeficiente');

        // IDs de copropietarios con asistencia confirmada
        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        // Coeficiente propio de los asistentes
        $coeficientePresente = Unidad::withoutGlobalScopes()
            ->whereIn('copropietario_id', $presenteIds)
            ->sum('coeficiente');

        // Coeficiente de los poderdantes representados por asistentes (Ley 675)
        $coeficienteDelegados = 0.0;
        if ($presenteIds->isNotEmpty()) {
            $poderdanteIds = Poder::withoutGlobalScopes()
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
                ->pluck('poderdante_id');

            if ($poderdanteIds->isNotEmpty()) {
                $coeficienteDelegados = (float) Unidad::withoutGlobalScopes()
                    ->whereIn('copropietario_id', $poderdanteIds)
                    ->sum('coeficiente');
            }
        }

        $coeficienteTotal = $coeficientePresente + $coeficienteDelegados;

        $porcentaje = $totalCoeficiente > 0
            ? round(($coeficienteTotal / $totalCoeficiente) * 100, 2)
            : 0;

        return [
            'tipo'                => 'coeficiente',
            'total'               => (float) $totalCoeficiente,
            'presente'            => (float) $coeficienteTotal,
            'porcentaje_presente' => $porcentaje,
            'quorum_requerido'    => (float) $reunion->quorum_requerido,
            'tiene_quorum'        => $porcentaje >= $reunion->quorum_requerido,
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

        // Poderdantes representados por asistentes (Ley 675)
        $poderdantesRepresentados = 0;
        if ($presenteIds->isNotEmpty()) {
            $poderdantesRepresentados = Poder::withoutGlobalScopes()
                ->where('estado', 'aprobado')
                ->whereIn('apoderado_id', $presenteIds)
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
