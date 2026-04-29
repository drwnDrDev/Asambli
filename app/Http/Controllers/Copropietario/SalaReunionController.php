<?php

namespace App\Http\Controllers\Copropietario;

use App\Enums\ReunionEstado;
use App\Events\QuorumActualizado;
use App\Http\Controllers\Controller;
use App\Models\Asistencia;
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
        $copropietario = auth('copropietario')->user();
        $reuniones = $copropietario
            ? Reunion::withoutGlobalScopes()
                ->where('tenant_id', $copropietario->tenant_id)
                ->whereIn('estado', ['convocada', 'ante_sala', 'en_curso'])
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return Inertia::render('Copropietario/Sala/Index', compact('reuniones'));
    }

    public function show(Reunion $reunion)
    {
        $copropietario = auth('copropietario')->user();

        // Bloquear si el copropietario tiene un poder aprobado activo
        if ($copropietario) {
            $poderActivo = Poder::withoutGlobalScopes()
                ->where('poderdante_id', $copropietario->id)
                ->where('estado', 'aprobado')
                ->with('apoderado')
                ->first();

            if ($poderActivo) {
                return Inertia::render('Copropietario/Sala/DelegadoActivo', [
                    'delegadoNombre'  => $poderActivo->apoderado?->nombre ?? 'Delegado',
                    'delegadoEmpresa' => $poderActivo->apoderado?->empresa,
                ]);
            }
        }

        // Auto-register attendance when copropietario enters ante_sala or en_curso
        $poderes = collect();
        if ($copropietario && in_array($reunion->estado, [ReunionEstado::AnteSala, ReunionEstado::EnCurso])) {
            // Registrar asistencia del copropietario que entra físicamente
            Asistencia::updateOrCreate(
                ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
                ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
            );

            // Obtener poderes aprobados
            $poderes = Poder::withoutGlobalScopes()
                ->where('apoderado_id', $copropietario->id)
                ->where('estado', 'aprobado')
                ->with('poderdante.unidades')
                ->get();

            // Registrar asistencia para cada poderdante representado
            foreach ($poderes as $poder) {
                Asistencia::updateOrCreate(
                    ['reunion_id' => $reunion->id, 'copropietario_id' => $poder->poderdante_id],
                    ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
                );
            }

            $quorum = $this->quorumService->calcular($reunion);
            broadcast(new QuorumActualizado($reunion->id, $quorum));
        } else {
            if ($copropietario) {
                $poderes = Poder::withoutGlobalScopes()
                    ->where('apoderado_id', $copropietario->id)
                    ->where('estado', 'aprobado')
                    ->with('poderdante.unidades')
                    ->get();
            }
            $quorum = $this->quorumService->calcular($reunion);
        }

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

        // Resultados actuales: only if already voted and there's an open votacion
        $resultadosActuales = null;
        if ($votacionAbierta && !empty($yaVotoPor)) {
            $resultadosActuales = $votacionAbierta->opciones->map(function ($opcion) use ($votacionAbierta) {
                $votos = \App\Models\Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacionAbierta->id)
                    ->where('opcion_id', $opcion->id);
                return [
                    'opcion_id'  => $opcion->id,
                    'texto'      => $opcion->texto,
                    'count'      => $votos->count(),
                    'peso_total' => (float) $votos->sum('peso'),
                ];
            })->toArray();
        }

        // Feed inicial: estado logs + closed votaciones with winner
        $feedInicial = $this->buildFeedInicial($reunion);

        $estadoReunion = $reunion->estado instanceof \App\Enums\ReunionEstado
            ? $reunion->estado->value
            : $reunion->estado;

        $esDelegadoExterno = $copropietario?->es_externo ?? false;

        $poderdantesRepresentados = $poderes->map(fn($p) => [
            'id'      => $p->poderdante_id,
            'nombre'  => $p->poderdante?->nombre,
            'unidades' => $p->poderdante?->unidades?->pluck('numero') ?? [],
        ])->values();

        return Inertia::render('Copropietario/Sala/Show', compact(
            'reunion', 'quorum', 'poderes', 'yaVotoPor', 'votacionAbierta',
            'resultadosActuales', 'feedInicial', 'estadoReunion', 'esDelegadoExterno',
            'poderdantesRepresentados'
        ));
    }

    private function buildFeedInicial(Reunion $reunion): array
    {
        $items = collect();

        // 1. Estado change logs from ReunionLog (no BelongsToTenant on ReunionLog, so withoutGlobalScopes is a no-op but kept for clarity)
        $logs = \App\Models\ReunionLog::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->where('accion', 'like', 'estado_cambiado_a_%')
            ->orderBy('created_at')
            ->get();

        foreach ($logs as $log) {
            $estado = str_replace('estado_cambiado_a_', '', $log->accion);
            $items->push([
                'tipo'      => 'estado_reunion',
                'estado'    => $estado,
                'timestamp' => $log->created_at->toIso8601String(),
            ]);
        }

        // 2. Avisos from logs
        $avisoLogs = \App\Models\ReunionLog::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->where('accion', 'aviso_enviado')
            ->orderBy('created_at')
            ->get();

        foreach ($avisoLogs as $log) {
            $items->push([
                'tipo'      => 'aviso',
                'mensaje'   => $log->metadata['mensaje'] ?? '',
                'timestamp' => $log->created_at->toIso8601String(),
            ]);
        }

        // 3. Closed votaciones with winner option
        $votacionesCerradas = $reunion->votaciones()
            ->with('opciones')
            ->where('estado', 'cerrada')
            ->orderBy('updated_at')
            ->get();

        foreach ($votacionesCerradas as $votacion) {
            $ganadora = null;
            $pesoMax = -1;
            $pesoTotal = 0;

            foreach ($votacion->opciones as $opcion) {
                $peso = (float) \App\Models\Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacion->id)
                    ->where('opcion_id', $opcion->id)
                    ->sum('peso');
                $pesoTotal += $peso;
                if ($peso > $pesoMax) {
                    $pesoMax = $peso;
                    $ganadora = $opcion;
                }
            }

            $pct = $pesoTotal > 0 ? round(($pesoMax / $pesoTotal) * 100, 1) : 0;

            $items->push([
                'tipo'         => 'votacion_cerrada',
                'votacion_id'  => $votacion->id,
                'pregunta'     => $votacion->pregunta,
                'ganadora'     => $ganadora?->texto,
                'ganadora_pct' => $pct,
                'timestamp'    => $votacion->updated_at->toIso8601String(),
            ]);
        }

        return $items->sortBy('timestamp')->values()->toArray();
    }

    public function estadoActual(Reunion $reunion)
    {
        $copropietario = auth('copropietario')->user();

        $votacionActiva = $reunion->votaciones()
            ->where('estado', 'abierta')
            ->with('opciones')
            ->first();

        $yaVote = false;
        if ($votacionActiva && $copropietario) {
            $yaVote = Voto::withoutGlobalScopes()
                ->where('votacion_id', $votacionActiva->id)
                ->where(function ($q) use ($copropietario) {
                    $q->where('copropietario_id', $copropietario->id)
                      ->orWhere('en_nombre_de', $copropietario->id);
                })
                ->exists();
        }

        return response()->json([
            'votacion_activa' => $votacionActiva,
            'ya_vote'         => $yaVote,
        ]);
    }

    public function historial()
    {
        $copropietario = auth('copropietario')->user();
        $tenantId = $copropietario?->tenant_id ?? app('current_tenant')->id;

        $reuniones = Reunion::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('estado', ['finalizada', 'cancelada', 'reprogramada'])
            ->latest()
            ->get();

        return Inertia::render('Copropietario/Sala/Historial', compact('reuniones'));
    }
}
