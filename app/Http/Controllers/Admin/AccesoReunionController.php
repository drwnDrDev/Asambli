<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoReunion;
use App\Models\Reunion;
use Inertia\Inertia;

class AccesoReunionController extends Controller
{
    public function show(Reunion $reunion)
    {
        $accesos = AccesoReunion::with(['copropietario.user', 'copropietario.unidades'])
            ->where('reunion_id', $reunion->id)
            ->where('activo', true)
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'nombre'           => $a->copropietario->user?->name ?? $a->copropietario->numero_documento,
                'numero_documento' => $a->copropietario->numero_documento,
                'unidades'         => $a->copropietario->unidades->pluck('numero')->join(', '),
                'pin'              => $a->pin_plain,
                'es_externo'       => $a->copropietario->es_externo,
            ]);

        return Inertia::render('Admin/Reuniones/ListaAcceso', [
            'reunion' => $reunion->only('id', 'titulo', 'estado', 'fecha_programada', 'convocatoria_envios'),
            'accesos' => $accesos,
        ]);
    }
}
