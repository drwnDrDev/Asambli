<?php

namespace App\Services;

use App\Models\AccesoReunion;
use App\Models\Poder;
use App\Notifications\AccesoDelegadoNotification;

class PoderService
{
    public function aprobar(Poder $poder, int $reunionId): void
    {
        $poder->update([
            'estado'       => 'aprobado',
            'aprobado_por' => auth()->id(),
        ]);

        $apoderado = $poder->apoderado;
        if (!$apoderado || !$reunionId) return;

        $pin = AccesoReunion::generarPin();

        AccesoReunion::updateOrCreate(
            ['copropietario_id' => $apoderado->id, 'reunion_id' => $reunionId],
            [
                'pin_hash'      => password_hash($pin, PASSWORD_BCRYPT),
                'pin_plain'     => $pin,
                'session_token' => null,
                'activo'        => true,
            ]
        );

        if ($apoderado->email) {
            $apoderado->notify(new AccesoDelegadoNotification($poder->reunion, $pin));
            $poder->update(['invitacion_enviada_at' => now()]);
        }
    }

    public function revocar(Poder $poder): void
    {
        $poder->update(['estado' => 'revocado']);

        $this->desactivarAccesosApoderado($poder);
    }

    public function desactivarAccesosApoderado(Poder $poder): void
    {
        AccesoReunion::where('copropietario_id', $poder->apoderado_id)
            ->whereHas('reunion', fn($q) => $q->where('tenant_id', $poder->tenant_id))
            ->update(['activo' => false, 'session_token' => null]);
    }
}
