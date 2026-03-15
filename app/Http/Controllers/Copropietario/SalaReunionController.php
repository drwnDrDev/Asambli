<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\Voto;
use App\Services\QuorumService;
use Inertia\Inertia;

class SalaReunionController extends Controller
{
    public function __construct(private QuorumService $quorumService) {}

    public function index()
    {
        $copropietario = Copropietario::where('user_id', auth()->id())->first();
        $reuniones = $copropietario
            ? Reunion::withoutGlobalScopes()
                ->where('tenant_id', $copropietario->tenant_id)
                ->whereIn('estado', ['convocada', 'en_curso'])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return Inertia::render('Copropietario/Sala/Index', compact('reuniones'));
    }

    public function show(Reunion $reunion)
    {
        $quorum = $this->quorumService->calcular($reunion);
        $copropietario = Copropietario::where('user_id', auth()->id())->first();

        $poderes = $copropietario
            ? Poder::withoutGlobalScopes()
                ->where('reunion_id', $reunion->id)
                ->where('apoderado_id', $copropietario->id)
                ->with('poderdante.user')
                ->get()
            : collect();

        $votacionAbierta = $reunion->votaciones()->with('opciones')->where('estado', 'abierta')->first();
        $yaVotoPor = [];
        if ($votacionAbierta && $copropietario) {
            $yaVotoPor = Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacionAbierta->id)
                ->where(function ($q) use ($copropietario) {
                    $q->where('copropietario_id', $copropietario->id)
                      ->orWhere('en_nombre_de', $copropietario->id);
                })
                ->pluck('en_nombre_de')
                ->map(fn($v) => $v ?? 'propio')
                ->toArray();
        }

        return Inertia::render('Copropietario/Sala/Show', compact('reunion', 'quorum', 'poderes', 'yaVotoPor', 'votacionAbierta'));
    }

    public function historial()
    {
        $copropietario = Copropietario::where('user_id', auth()->id())->first();
        $reuniones = $copropietario
            ? Reunion::withoutGlobalScopes()
                ->where('tenant_id', $copropietario->tenant_id)
                ->where('estado', 'finalizada')
                ->orderByDesc('fecha_fin')
                ->get()
            : collect();

        return Inertia::render('Copropietario/Sala/Historial', compact('reuniones'));
    }
}
