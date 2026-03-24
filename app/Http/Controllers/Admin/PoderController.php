<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\User;
use App\Notifications\PoderAsignadoCopropietarioNotification;
use App\Notifications\PoderDelegadoInvitation;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PoderController extends Controller
{
    public function index()
    {
        $poderes = Poder::withoutGlobalScopes()
            ->where('tenant_id', app('current_tenant')->id)
            ->with('apoderado.user', 'poderdante.user', 'poderdante.unidades', 'registradoPor', 'aprobadoPor')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('estado');

        $copropietarios = Copropietario::with('user', 'unidades')
            ->where('es_externo', false)
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Poderes/Index', [
            'poderes'        => $poderes,
            'copropietarios' => $copropietarios,
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
        // Caso A: delegado es copropietario existente
        if ($request->filled('apoderado_copropietario_id')) {
            $data = $request->validate([
                'poderdante_id'            => 'required|integer|exists:copropietarios,id',
                'apoderado_copropietario_id' => 'required|integer|exists:copropietarios,id',
                'documento_url'            => 'nullable|string|max:500',
            ]);

            $apoderado = Copropietario::findOrFail($data['apoderado_copropietario_id']);
            $elegibilidad = $this->verificarElegibilidadApoderado($apoderado, app('current_tenant')->max_poderes_por_delegado ?? 2);

            if ($elegibilidad['bloqueado']) {
                return back()->withErrors(['apoderado_copropietario_id' => $elegibilidad['motivo']]);
            }

            $poder = Poder::create([
                'tenant_id'      => app('current_tenant')->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $data['poderdante_id'],
                'documento_url'  => $data['documento_url'] ?? null,
                'registrado_por' => auth()->id(),
                'estado'         => 'aprobado',
                'aprobado_por'   => auth()->id(),
            ]);

            $this->enviarNotificacion($poder->load('apoderado.user', 'poderdante.user'));

            return back()->with('success', 'Poder registrado. El copropietario ha sido notificado.');
        }

        // Caso B: delegado externo
        $data = $request->validate([
            'poderdante_id'      => 'required|integer|exists:copropietarios,id',
            'delegado_nombre'    => 'required|string|max:255',
            'delegado_email'     => 'required|email',
            'delegado_documento' => 'required|string|max:50',
            'delegado_telefono'  => 'nullable|string|max:30',
            'delegado_empresa'   => 'nullable|string|max:150',
            'documento_url'      => 'nullable|string|max:500',
        ]);

        $tenant = app('current_tenant');
        $poder = null;

        DB::transaction(function () use ($data, $tenant, &$poder) {
            $apoderado = $this->resolverOCrearExterno($data, $tenant);

            $poder = Poder::create([
                'tenant_id'      => $tenant->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $data['poderdante_id'],
                'documento_url'  => $data['documento_url'] ?? null,
                'registrado_por' => auth()->id(),
                'estado'         => 'aprobado',
                'aprobado_por'   => auth()->id(),
            ]);
        });

        $this->enviarNotificacion($poder->load('apoderado.user', 'poderdante.user'));

        return back()->with('success', 'Poder registrado y delegado invitado.');
    }

    public function aprobar(Poder $poder)
    {
        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);
        abort_if($poder->estado !== 'pendiente', 422, 'El poder no está pendiente de aprobación.');

        $poder->update([
            'estado'       => 'aprobado',
            'aprobado_por' => auth()->id(),
        ]);

        $this->enviarNotificacion($poder->load('apoderado.user', 'poderdante.user'));

        return back()->with('success', 'Poder aprobado.');
    }

    public function rechazar(Request $request, Poder $poder)
    {
        $data = $request->validate(['motivo' => 'nullable|string|max:500']);

        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);
        abort_if(!in_array($poder->estado, ['pendiente', 'aprobado']), 422);

        $poder->update([
            'estado'           => 'rechazado',
            'rechazado_motivo' => $data['motivo'] ?? null,
        ]);

        return back()->with('success', 'Poder rechazado.');
    }

    public function destroy(Poder $poder)
    {
        abort_if($poder->tenant_id !== app('current_tenant')->id, 404);

        $poder->update(['estado' => 'revocado']);

        return back()->with('success', 'Poder revocado.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function verificarElegibilidadApoderado(Copropietario $copropietario, int $maxPoderes): array
    {
        // 1. ¿Ya otorgó su propio poder?
        $esPoderdante = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($esPoderdante) {
            return [
                'elegible'  => false,
                'bloqueado' => true,
                'motivo'    => 'Este copropietario ya delegó su propio voto, no puede actuar como delegado.',
                'info'      => null,
                'poderes_actuales' => 0,
            ];
        }

        // 2. ¿Ya alcanzó el máximo de poderes recibidos?
        $poderesActuales = Poder::withoutGlobalScopes()
            ->where('apoderado_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->count();

        if ($poderesActuales >= $maxPoderes) {
            return [
                'elegible'  => false,
                'bloqueado' => true,
                'motivo'    => "Este delegado ya tiene el máximo de {$maxPoderes} poderes activos.",
                'info'      => null,
                'poderes_actuales' => $poderesActuales,
            ];
        }

        // 3. Ya tiene poderes pero dentro del límite — informativo
        $info = $poderesActuales > 0
            ? "Este copropietario ya representa a {$poderesActuales} persona(s)."
            : null;

        return [
            'elegible'  => true,
            'bloqueado' => false,
            'motivo'    => null,
            'info'      => $info,
            'poderes_actuales' => $poderesActuales,
        ];
    }

    private function enviarNotificacion(Poder $poder): void
    {
        $apoderado = $poder->apoderado;
        if (!$apoderado) return;

        $user = $apoderado->user;
        if (!$user) return;

        if ($apoderado->es_externo) {
            // Externo: onboarding invitation
            $url = app(MagicLinkService::class)->generate($user, null, 'onboarding');
            $user->notify(new PoderDelegadoInvitation($url, $poder));
            $poder->update(['invitacion_enviada_at' => now()]);
        } else {
            // Copropietario existente: notificación simple con link a /sala
            $url = app(MagicLinkService::class)->generate($user, null, 'convocatoria');
            $user->notify(new PoderAsignadoCopropietarioNotification($url, $poder));
            $poder->update(['invitacion_enviada_at' => now()]);
        }
    }

    private function resolverOCrearExterno(array $data, $tenant): Copropietario
    {
        // Buscar por email dentro del tenant
        $user = User::where('email', $data['delegado_email'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($user) {
            $copropietario = Copropietario::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->first();
            if ($copropietario) {
                return $copropietario;
            }
        }

        // Buscar por documento dentro del tenant
        $existePorDoc = Copropietario::withoutGlobalScopes()
            ->whereHas('user', fn($q) => $q->where('tenant_id', $tenant->id))
            ->where('numero_documento', $data['delegado_documento'])
            ->first();

        if ($existePorDoc) {
            return $existePorDoc;
        }

        // Crear nuevo usuario externo
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['delegado_nombre'],
            'email'     => $data['delegado_email'],
            'password'  => bcrypt(Str::random(20)),
            'rol'       => 'copropietario',
        ]);

        return Copropietario::create([
            'tenant_id'        => $tenant->id,
            'user_id'          => $user->id,
            'tipo_documento'   => null,
            'numero_documento' => $data['delegado_documento'],
            'telefono'         => $data['delegado_telefono'] ?? null,
            'empresa'          => $data['delegado_empresa'] ?? null,
            'es_externo'       => true,
            'es_residente'     => false,
            'activo'           => true,
        ]);
    }
}
