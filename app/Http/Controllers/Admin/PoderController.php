<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Services\PoderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PoderController extends Controller
{
    public function __construct(private readonly PoderService $poderService) {}

    public function index()
    {
        $tenant = app('current_tenant');

        $poderes = Poder::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with('apoderado.unidades', 'poderdante.unidades', 'registradoPor', 'aprobadoPor')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('estado');

        $copropietarios = Copropietario::with('unidades')
            ->where('es_externo', false)
            ->orderBy('id')
            ->get();

        $reunionesVigentes = Reunion::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('estado', ['convocada', 'ante_sala', 'en_curso'])
            ->latest()
            ->get(['id', 'titulo', 'estado', 'fecha_programada']);

        return Inertia::render('Admin/Poderes/Index', [
            'poderes'           => $poderes,
            'copropietarios'    => $copropietarios,
            'reunionesVigentes' => $reunionesVigentes,
        ]);
    }

    public function verificarDelegado(Request $request)
    {
        $id = $request->integer('copropietario_id');
        $copropietario = Copropietario::with('unidades')->findOrFail($id);
        $tenant = app('current_tenant');
        $maxPoderes = $tenant->max_poderes_por_delegado ?? 2;

        return response()->json($this->verificarElegibilidadApoderado($copropietario, $maxPoderes));
    }

    public function store(Request $request)
    {
        $tenant = app('current_tenant');

        $reunionId = $request->integer('reunion_id');
        $reunion = $reunionId
            ? Reunion::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereIn('estado', ['convocada', 'ante_sala', 'en_curso'])
                ->find($reunionId)
            : null;

        if (!$reunion) {
            return back()->withErrors(['reunion_id' => 'Debe seleccionar una reunión vigente para crear el poder.']);
        }

        // Caso A: delegado es copropietario existente
        if ($request->filled('apoderado_copropietario_id')) {
            $data = $request->validate([
                'reunion_id'                 => 'required|integer|exists:reuniones,id',
                'poderdante_id'              => 'required|integer|exists:copropietarios,id',
                'apoderado_copropietario_id' => 'required|integer|exists:copropietarios,id',
                'documento_url'              => 'nullable|string|max:500',
            ]);

            $apoderado = Copropietario::findOrFail($data['apoderado_copropietario_id']);
            $elegibilidad = $this->verificarElegibilidadApoderado($apoderado, $tenant->max_poderes_por_delegado ?? 2);

            if ($elegibilidad['bloqueado']) {
                return back()->withErrors(['apoderado_copropietario_id' => $elegibilidad['motivo']]);
            }

            $poder = Poder::create([
                'tenant_id'      => $tenant->id,
                'reunion_id'     => $reunion->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $data['poderdante_id'],
                'documento_url'  => $data['documento_url'] ?? null,
                'registrado_por' => auth()->id(),
                'estado'         => 'aprobado',
                'aprobado_por'   => auth()->id(),
            ]);

            $this->poderService->aprobar($poder->fresh(), $reunion->id);

            return back()->with('success', 'Poder registrado. El copropietario ha sido notificado.');
        }

        // Caso B: delegado externo
        $data = $request->validate([
            'reunion_id'              => 'required|integer|exists:reuniones,id',
            'poderdante_id'           => 'required|integer|exists:copropietarios,id',
            'delegado_nombre'         => 'required|string|max:255',
            'delegado_tipo_documento' => 'required|in:CC,CE,NIT,PP,TI,PEP',
            'delegado_email'          => 'required|email',
            'delegado_documento'      => 'required|string|max:50',
            'delegado_telefono'       => 'nullable|string|max:30',
            'delegado_empresa'        => 'nullable|string|max:150',
            'documento_url'           => 'nullable|string|max:500',
        ]);

        $poder = null;

        DB::transaction(function () use ($data, $tenant, $reunion, &$poder) {
            $apoderado = $this->resolverOCrearExterno($data, $tenant);

            $poder = Poder::create([
                'tenant_id'      => $tenant->id,
                'reunion_id'     => $reunion->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $data['poderdante_id'],
                'documento_url'  => $data['documento_url'] ?? null,
                'registrado_por' => auth()->id(),
                'estado'         => 'aprobado',
                'aprobado_por'   => auth()->id(),
            ]);
        });

        $this->poderService->aprobar($poder->fresh(), $reunion->id);

        return back()->with('success', 'Poder registrado y delegado notificado con PIN de acceso.');
    }

    public function aprobar(Poder $poder)
    {
        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);
        abort_if($poder->estado !== 'pendiente', 422, 'El poder no está pendiente de aprobación.');

        $this->poderService->aprobar($poder, $poder->reunion_id);

        return back()->with('success', 'Poder aprobado.');
    }

    public function rechazar(Request $request, Poder $poder)
    {
        $data = $request->validate(['motivo' => 'nullable|string|max:500']);

        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);
        abort_if(!in_array($poder->estado, ['pendiente', 'aprobado']), 422);

        $eraAprobado = $poder->estado === 'aprobado';

        $poder->update([
            'estado'           => 'rechazado',
            'rechazado_motivo' => $data['motivo'] ?? null,
        ]);

        if ($eraAprobado) {
            $this->poderService->desactivarAccesosApoderado($poder);
        }

        return back()->with('success', 'Poder rechazado.');
    }

    public function destroy(Poder $poder)
    {
        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);

        $this->poderService->revocar($poder);

        return back()->with('success', 'Poder revocado.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function verificarElegibilidadApoderado(Copropietario $copropietario, int $maxPoderes): array
    {
        $esPoderdante = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($esPoderdante) {
            return [
                'elegible'         => false,
                'bloqueado'        => true,
                'motivo'           => 'Este copropietario ya delegó su propio voto, no puede actuar como delegado.',
                'info'             => null,
                'poderes_actuales' => 0,
            ];
        }

        $poderesActuales = Poder::withoutGlobalScopes()
            ->where('apoderado_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->count();

        if ($poderesActuales >= $maxPoderes) {
            return [
                'elegible'         => false,
                'bloqueado'        => true,
                'motivo'           => "Este delegado ya tiene el máximo de {$maxPoderes} poderes activos.",
                'info'             => null,
                'poderes_actuales' => $poderesActuales,
            ];
        }

        $info = $poderesActuales > 0
            ? "Este copropietario ya representa a {$poderesActuales} persona(s)."
            : null;

        return [
            'elegible'         => true,
            'bloqueado'        => false,
            'motivo'           => null,
            'info'             => $info,
            'poderes_actuales' => $poderesActuales,
        ];
    }

    private function resolverOCrearExterno(array $data, $tenant): Copropietario
    {
        // Buscar por email en copropietarios del tenant
        $copropietario = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $data['delegado_email'])
            ->first();
        if ($copropietario) return $copropietario;

        // Buscar por tipo + numero de documento
        $copropietario = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('tipo_documento', $data['delegado_tipo_documento'])
            ->where('numero_documento', $data['delegado_documento'])
            ->first();
        if ($copropietario) return $copropietario;

        // Crear externo sin User
        return Copropietario::create([
            'tenant_id'        => $tenant->id,
            'nombre'           => $data['delegado_nombre'],
            'email'            => $data['delegado_email'],
            'tipo_documento'   => $data['delegado_tipo_documento'],
            'numero_documento' => $data['delegado_documento'],
            'telefono'         => $data['delegado_telefono'] ?? null,
            'empresa'          => $data['delegado_empresa'] ?? null,
            'es_externo'       => true,
            'es_residente'     => false,
            'activo'           => true,
        ]);
    }
}
