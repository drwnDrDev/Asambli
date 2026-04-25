<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Votacion;
use App\Services\VotoService;
use Illuminate\Http\Request;

class VotoController extends Controller
{
    public function __construct(private VotoService $votoService) {}

    public function store(Request $request)
    {
        $request->validate([
            'votacion_id' => 'required|integer',
            'opcion_id' => 'required|integer',
            'en_nombre_de' => 'nullable|integer',
        ]);

        $votacion = Votacion::findOrFail($request->votacion_id);
        $copropietario = auth('copropietario')->user()
            ?? Copropietario::where('user_id', auth()->id())->first();

        abort_if(!$copropietario, 403, 'No autenticado como copropietario.');

        $result = $this->votoService->votar(
            $votacion,
            $copropietario,
            $request->opcion_id,
            $request,
            $request->en_nombre_de
        );

        if (!$result['success']) {
            return back()->withErrors(['voto' => $result['error']]);
        }

        return back()->with('success', 'Voto registrado correctamente.');
    }
}
