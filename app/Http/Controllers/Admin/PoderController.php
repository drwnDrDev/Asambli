<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\User;
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'poderdante_id'     => 'required|integer|exists:copropietarios,id',
            'delegado_nombre'   => 'required|string|max:255',
            'delegado_email'    => 'required|email',
            'delegado_telefono' => 'nullable|string|max:30',
            'delegado_documento'=> 'nullable|string|max:50',
            'delegado_empresa'  => 'nullable|string|max:150',
            'documento_url'     => 'nullable|string|max:500',
        ]);

        $tenant = app('current_tenant');
        $poder = null;

        DB::transaction(function () use ($data, $tenant, &$poder) {
            $apoderado = $this->resolverOCrearDelegado($data, $tenant);

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

        $this->enviarInvitacion($poder);

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

        $this->enviarInvitacion($poder->load('apoderado.user'));

        return back()->with('success', 'Poder aprobado e invitación enviada.');
    }

    public function rechazar(Request $request, Poder $poder)
    {
        $data = $request->validate([
            'motivo' => 'nullable|string|max:500',
        ]);

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

    private function resolverOCrearDelegado(array $data, $tenant): Copropietario
    {
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
            'numero_documento' => $data['delegado_documento'] ?? null,
            'telefono'         => $data['delegado_telefono'] ?? null,
            'empresa'          => $data['delegado_empresa'] ?? null,
            'es_externo'       => true,
            'es_residente'     => false,
            'activo'           => true,
        ]);
    }

    private function enviarInvitacion(Poder $poder): void
    {
        $apoderado = $poder->apoderado ?? $poder->load('apoderado.user')->apoderado;

        if (!$apoderado?->es_externo) {
            return;
        }

        $user = $apoderado->user;
        $url = app(MagicLinkService::class)->generate($user, null, 'onboarding');
        $user->notify(new PoderDelegadoInvitation($url, $poder));

        $poder->update(['invitacion_enviada_at' => now()]);
    }
}
