<?php

namespace App\Services;

use App\Enums\ReunionEstado;
use App\Models\Reunion;
use App\Models\ReunionLog;
use App\Models\User;

class ReunionTransicionService
{
    public function transicionar(
        Reunion $reunion,
        ReunionEstado $nuevoEstado,
        User $user,
        string $observacion,
        array $metadata = []
    ): void {
        if (trim($observacion) === '') {
            throw new \InvalidArgumentException('La observación es requerida para cambiar el estado.');
        }

        $estadoActual = $reunion->estado instanceof ReunionEstado
            ? $reunion->estado
            : ReunionEstado::from($reunion->estado);

        $permitidos = $estadoActual->transicionesPermitidas();

        if (! in_array($nuevoEstado, $permitidos, true)) {
            throw new \InvalidArgumentException(
                "Transición no permitida: {$estadoActual->value} → {$nuevoEstado->value}"
            );
        }

        $reunion->estado = $nuevoEstado;

        if ($nuevoEstado === ReunionEstado::EnCurso) {
            $reunion->fecha_inicio = now();
        }

        if ($nuevoEstado === ReunionEstado::Finalizada) {
            $reunion->fecha_fin = now();
        }

        $reunion->save();

        ReunionLog::create([
            'reunion_id'  => $reunion->id,
            'user_id'     => $user->id,
            'accion'      => "estado_cambiado_a_{$nuevoEstado->value}",
            'observacion' => $observacion,
            'metadata'    => array_merge($metadata, ['estado_anterior' => $estadoActual->value]),
        ]);

        broadcast(new \App\Events\EstadoReunionCambiado($reunion, $nuevoEstado->value));
    }
}
