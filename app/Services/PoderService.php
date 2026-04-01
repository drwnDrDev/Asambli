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

        // Notify only if has email
        if ($apoderado->email) {
            $apoderado->notify(new AccesoDelegadoNotification($poder->reunion, $pin));
        }
    }

    public function revocar(Poder $poder): void
    {
        $poder->update(['estado' => 'revocado']);

        AccesoReunion::where('copropietario_id', $poder->apoderado_id)
            ->where('reunion_id', $poder->reunion_id)
            ->update(['activo' => false]);
    }
}
