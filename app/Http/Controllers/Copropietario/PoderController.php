<?php

namespace App\Http\Controllers\Copropietario;

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
        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();

        $miPoder = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->with('apoderado.user')
            ->latest()
            ->first();

        return Inertia::render('Copropietario/Sala/Poderes/Index', [
            'miPoder' => $miPoder,
        ]);
    }

    public function create()
    {
        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();

        $yaActivo = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        // Lista de copropietarios del tenant (sin emails por privacidad)
        $copropietarios = Copropietario::with('user', 'unidades')
            ->where('es_externo', false)
            ->where('id', '!=', $copropietario->id)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'nombre'           => $c->user?->name,
                'numero_documento' => $c->numero_documento,
                'unidades'         => $c->unidades->map(fn($u) => ['numero' => $u->numero]),
            ]);

        return Inertia::render('Copropietario/Sala/Poderes/Create', [
            'yaActivo'       => $yaActivo,
            'copropietarios' => $copropietarios,
        ]);
    }

    public function verificarDelegado(Request $request)
    {
        $id = $request->integer('copropietario_id');
        $copropietario = Copropietario::findOrFail($id);
        $tenant = app('current_tenant');
        $maxPoderes = $tenant->max_poderes_por_delegado ?? 2;

        return response()->json($this->verificarElegibilidad($copropietario, $maxPoderes));
    }

    public function store(Request $request)
    {
        $yo = Copropietario::where('user_id', auth()->id())->firstOrFail();
        $tenant = app('current_tenant');

        // Caso A: delegado es copropietario existente
        if ($request->filled('apoderado_copropietario_id')) {
            $data = $request->validate([
                'apoderado_copropietario_id' => 'required|integer|exists:copropietarios,id',
            ]);

            $apoderado = Copropietario::findOrFail($data['apoderado_copropietario_id']);
            $elegibilidad = $this->verificarElegibilidad($apoderado, $tenant->max_poderes_por_delegado ?? 2);

            if ($elegibilidad['bloqueado']) {
                return back()->withErrors(['apoderado_copropietario_id' => $elegibilidad['motivo']]);
            }

            $poder = Poder::create([
                'tenant_id'      => $tenant->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $yo->id,
                'registrado_por' => auth()->id(),
                'estado'         => 'pendiente',
            ]);

            return redirect()->route('sala.poderes.index')
                ->with('success', 'Solicitud enviada. El administrador la revisará pronto.');
        }

        // Caso B: delegado externo
        $data = $request->validate([
            'delegado_nombre'    => 'required|string|max:255',
            'delegado_email'     => 'required|email',
            'delegado_documento' => 'required|string|max:50',
            'delegado_telefono'  => 'nullable|string|max:30',
            'delegado_empresa'   => 'nullable|string|max:150',
        ]);

        DB::transaction(function () use ($data, $yo, $tenant) {
            $apoderado = $this->resolverOCrearExterno($data, $tenant);

            Poder::create([
                'tenant_id'      => $tenant->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $yo->id,
                'registrado_por' => auth()->id(),
                'estado'         => 'pendiente',
            ]);
        });

        return redirect()->route('sala.poderes.index')
            ->with('success', 'Solicitud enviada. El administrador la revisará pronto.');
    }

    public function destroy(Poder $poder)
    {
        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();

        abort_if($poder->poderdante_id !== $copropietario->id, 403);
        abort_if($poder->estado !== 'pendiente', 422, 'Solo puedes retirar poderes pendientes de aprobación.');

        $poder->update(['estado' => 'rechazado', 'rechazado_motivo' => 'Retirado por el copropietario']);

        return redirect()->route('sala.poderes.index')
            ->with('success', 'Solicitud de poder retirada.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function verificarElegibilidad(Copropietario $copropietario, int $maxPoderes): array
    {
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

        return [
            'elegible'  => true,
            'bloqueado' => false,
            'motivo'    => null,
            'info'      => $poderesActuales > 0 ? "Este copropietario ya representa a {$poderesActuales} persona(s)." : null,
            'poderes_actuales' => $poderesActuales,
        ];
    }

    private function resolverOCrearExterno(array $data, $tenant): Copropietario
    {
        $user = User::where('email', $data['delegado_email'])
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($user) {
            $c = Copropietario::withoutGlobalScopes()->where('user_id', $user->id)->first();
            if ($c) return $c;
        }

        $existePorDoc = Copropietario::withoutGlobalScopes()
            ->whereHas('user', fn($q) => $q->where('tenant_id', $tenant->id))
            ->where('numero_documento', $data['delegado_documento'])
            ->first();

        if ($existePorDoc) return $existePorDoc;

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
            'numero_documento' => $data['delegado_documento'],
            'telefono'         => $data['delegado_telefono'] ?? null,
            'empresa'          => $data['delegado_empresa'] ?? null,
            'es_externo'       => true,
            'es_residente'     => false,
            'activo'           => true,
        ]);
    }
}
