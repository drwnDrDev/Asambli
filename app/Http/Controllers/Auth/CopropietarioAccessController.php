<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Reunion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CopropietarioAccessController extends Controller
{
    public function show(Reunion $reunion)
    {
        return Inertia::render('Sala/Login', [
            'reunion' => [
                'id'               => $reunion->id,
                'titulo'           => $reunion->titulo,
                'fecha_programada' => $reunion->fecha_programada?->format('d/m/Y H:i'),
                'tenant'           => ['nombre' => $reunion->tenant->nombre],
            ],
        ]);
    }

    public function store(Request $request, Reunion $reunion)
    {
        $request->validate([
            'numero_documento' => 'required|string',
            'pin'              => 'required|string|size:6',
        ]);

        $copropietario = Copropietario::where('tenant_id', $reunion->tenant_id)
            ->where('numero_documento', $request->numero_documento)
            ->where('activo', true)
            ->first();

        if (!$copropietario) {
            return back()->withErrors(['pin' => 'Documento o PIN incorrecto.']);
        }

        $acceso = $copropietario->accesoParaReunion($reunion->id);

        if (!$acceso || !$acceso->verificarPin($request->pin)) {
            return back()->withErrors(['pin' => 'Documento o PIN incorrecto.']);
        }

        $token = $acceso->rotarToken();
        $request->session()->put('copropietario_session_token', $token);

        return redirect()->route('sala.show', $reunion->id);
    }
}
