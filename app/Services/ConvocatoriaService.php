<?php

namespace App\Services;

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Notifications\AccesoReunionNotification;

class ConvocatoriaService
{
    public function enviar(Reunion $reunion): void
    {
        if (! $reunion->puedeConvocar()) {
            throw new \RuntimeException('Se alcanzó el límite de convocatorias para esta reunión.');
        }

        $copropietarios = Copropietario::where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->whereNotNull('email')
            ->get();

        foreach ($copropietarios as $copropietario) {
            $pin = AccesoReunion::generarPin();

            AccesoReunion::updateOrCreate(
                ['copropietario_id' => $copropietario->id, 'reunion_id' => $reunion->id],
                [
                    'pin_hash'      => password_hash($pin, PASSWORD_BCRYPT),
                    'pin_plain'     => $pin,
                    'session_token' => null,
                    'activo'        => true,
                ]
            );

            $copropietario->notify(new AccesoReunionNotification($reunion, $pin));
        }

        $reunion->increment('convocatoria_envios');

        $reunion->logs()->create([
            'accion'      => 'convocatoria_enviada',
            'observacion' => "Convocatoria #{$reunion->convocatoria_envios} enviada a {$copropietarios->count()} copropietarios.",
            'user_id'     => auth()->id(),
            'metadata'    => ['total_notificados' => $copropietarios->count()],
        ]);
    }
}
