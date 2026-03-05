<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpcionVotacion;
use App\Models\Reunion;
use App\Models\Votacion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class VotacionController extends Controller
{
    public function store(Request $request, Reunion $reunion)
    {
        $data = $request->validate([
            'pregunta' => 'required|string|max:500',
            'opciones' => 'required|array|min:2',
            'opciones.*.texto' => 'required|string|max:255',
        ]);

        $votacion = $reunion->votaciones()->create([
            'pregunta' => $data['pregunta'],
            'estado' => 'pendiente',
            'tenant_id' => $reunion->tenant_id,
        ]);

        foreach ($data['opciones'] as $opcion) {
            $votacion->opciones()->create(['texto' => $opcion['texto']]);
        }

        return back()->with('success', 'Votación creada.');
    }

    public function abrir(Votacion $votacion)
    {
        $votacion->update(['estado' => 'abierta', 'abierta_at' => now()]);
        broadcast(new \App\Events\EstadoVotacionCambiado($votacion));
        return back()->with('success', 'Votación abierta.');
    }

    public function cerrar(Votacion $votacion)
    {
        $votacion->update(['estado' => 'cerrada', 'cerrada_at' => now()]);
        broadcast(new \App\Events\EstadoVotacionCambiado($votacion));
        return back()->with('success', 'Votación cerrada.');
    }

    public function resultados(Votacion $votacion)
    {
        $votacion->load('opciones.votos', 'reunion');
        return Inertia::render('Admin/Votaciones/Resultados', compact('votacion'));
    }
}
