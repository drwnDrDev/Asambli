<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Copropietario;
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

        $presenteIds = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->pluck('copropietario_id');

        $coeficientePresente = Copropietario::withoutGlobalScopes()
            ->whereIn('copropietarios.id', $presenteIds)
            ->join('unidades', 'copropietarios.unidad_id', '=', 'unidades.id')
            ->sum('unidades.coeficiente');

        $porcentaje = $totalCoeficiente > 0
            ? round(($coeficientePresente / $totalCoeficiente) * 100, 2)
            : 0;

        return [
            'tipo' => 'coeficiente',
            'total' => (float) $totalCoeficiente,
            'presente' => (float) $coeficientePresente,
            'porcentaje_presente' => $porcentaje,
            'quorum_requerido' => (float) $reunion->quorum_requerido,
            'tiene_quorum' => $porcentaje >= $reunion->quorum_requerido,
        ];
    }

    private function calcularPorUnidad(Reunion $reunion): array
    {
        $totalUnidades = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->count();

        $presentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->count();

        $porcentaje = $totalUnidades > 0
            ? round(($presentes / $totalUnidades) * 100, 2)
            : 0;

        return [
            'tipo' => 'unidad',
            'total' => $totalUnidades,
            'presente' => $presentes,
            'porcentaje_presente' => $porcentaje,
            'quorum_requerido' => (float) $reunion->quorum_requerido,
            'tiene_quorum' => $porcentaje >= $reunion->quorum_requerido,
        ];
    }
}
