<?php

namespace App\Http\Controllers\Admin;

use App\Events\VotacionModificada;
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
            'pregunta'    => 'required|string|max:500',
            'descripcion' => 'nullable|string|max:2000',
            'opciones'    => 'required|array|min:2',
            'opciones.*.texto' => 'required|string|max:255',
        ]);

        $votacion = $reunion->votaciones()->create([
            'pregunta'    => $data['pregunta'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado'      => 'creada',
            'creada_por'  => auth()->id(),
            'tenant_id'   => $reunion->tenant_id,
        ]);

        foreach ($data['opciones'] as $opcion) {
            $votacion->opciones()->create(['texto' => $opcion['texto']]);
        }

        $votacion->load('opciones');
        broadcast(new VotacionModificada($votacion, 'created'));

        return back()->with('success', 'Votación creada.');
    }

    public function update(Request $request, Votacion $votacion)
    {
        if ($votacion->estado !== 'creada') {
            abort(403, 'Solo se pueden editar votaciones en estado creada.');
        }

        $data = $request->validate([
            'pregunta'    => 'required|string|max:500',
            'descripcion' => 'nullable|string|max:2000',
            'opciones'    => 'required|array|min:2',
            'opciones.*.texto' => 'required|string|max:255',
        ]);

        $votacion->update([
            'pregunta'    => $data['pregunta'],
            'descripcion' => $data['descripcion'] ?? null,
        ]);

        $votacion->opciones()->delete();

        foreach ($data['opciones'] as $opcion) {
            $votacion->opciones()->create(['texto' => $opcion['texto']]);
        }

        $votacion->load('opciones');
        broadcast(new VotacionModificada($votacion, 'updated'));

        return back()->with('success', 'Votación actualizada.');
    }

    public function destroy(Votacion $votacion)
    {
        if ($votacion->estado !== 'creada') {
            abort(403, 'Solo se pueden eliminar votaciones en estado creada.');
        }

        $votacion->load('opciones');
        $reunionId = $votacion->reunion_id;

        // Snapshot para el broadcast antes de eliminar
        $payload = new VotacionModificada($votacion, 'deleted');

        $votacion->opciones()->delete();
        $votacion->delete();

        broadcast($payload);

        return back()->with('success', 'Votación eliminada.');
    }

    public function abrir(Votacion $votacion)
    {
        $votacion->update(['estado' => 'abierta', 'abierta_at' => now()]);
        $votacion->load('opciones');
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
