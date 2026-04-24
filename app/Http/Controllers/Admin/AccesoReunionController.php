<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoReunion;
use App\Models\Reunion;
use App\Notifications\AccesoReunionNotification;
use Inertia\Inertia;

class AccesoReunionController extends Controller
{
    public function show(Reunion $reunion)
    {
        // Excluir delegados externos cuyo poder fue revocado/rechazado y no tienen uno activo
        $accesos = AccesoReunion::with(['copropietario.unidades'])
            ->where('reunion_id', $reunion->id)
            ->where(fn($q) =>
                $q->whereHas('copropietario', fn($c) => $c->where('es_externo', false))
                  ->orWhereHas('copropietario', fn($c) =>
                      $c->where('es_externo', true)
                        ->whereHas('poderesComoApoderado', fn($p) =>
                            $p->where('tenant_id', $reunion->tenant_id)
                              ->where('estado', 'aprobado')
                        )
                  )
            )
            ->orderBy('activo', 'desc')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'nombre'           => $a->copropietario->email
                                        ? ($a->copropietario->email)
                                        : $a->copropietario->numero_documento,
                'numero_documento' => $a->copropietario->numero_documento,
                'email'            => $a->copropietario->email,
                'unidades'         => $a->copropietario->unidades->pluck('numero')->join(', '),
                'pin'              => $a->pin_plain,
                'activo'           => $a->activo,
                'es_externo'       => $a->copropietario->es_externo,
            ]);

        return Inertia::render('Admin/Reuniones/ListaAcceso', [
            'reunion' => $reunion->only('id', 'titulo', 'estado', 'fecha_programada', 'convocatoria_envios'),
            'accesos' => $accesos,
        ]);
    }

    public function reenviar(Reunion $reunion, AccesoReunion $acceso)
    {
        abort_if($acceso->reunion_id !== $reunion->id, 404);
        abort_unless($acceso->copropietario->email, 422, 'Este copropietario no tiene email registrado.');

        $pin = AccesoReunion::generarPin();

        $acceso->update([
            'pin_hash'      => password_hash($pin, PASSWORD_BCRYPT),
            'pin_plain'     => $pin,
            'session_token' => null,
            'activo'        => true,
        ]);

        $acceso->copropietario->notify(new AccesoReunionNotification($reunion, $pin));

        return back()->with('success', "PIN regenerado y enviado a {$acceso->copropietario->email}.");
    }

    public function desactivar(Reunion $reunion, AccesoReunion $acceso)
    {
        abort_if($acceso->reunion_id !== $reunion->id, 404);

        $acceso->update(['activo' => false, 'session_token' => null]);

        return back()->with('success', 'Acceso desactivado.');
    }

    public function activar(Reunion $reunion, AccesoReunion $acceso)
    {
        abort_if($acceso->reunion_id !== $reunion->id, 404);

        $acceso->update(['activo' => true]);

        return back()->with('success', 'Acceso reactivado.');
    }
}
