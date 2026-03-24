<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\User;
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

        return Inertia::render('Copropietario/Sala/Poderes/Create', [
            'yaActivo' => $yaActivo,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'delegado_nombre'   => 'required|string|max:255',
            'delegado_email'    => 'required|email',
            'delegado_telefono' => 'nullable|string|max:30',
            'delegado_documento'=> 'nullable|string|max:50',
            'delegado_empresa'  => 'nullable|string|max:150',
        ]);

        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();
        $tenant = app('current_tenant');

        DB::transaction(function () use ($data, $copropietario, $tenant) {
            $apoderado = $this->resolverOCrearDelegado($data, $tenant);

            Poder::create([
                'tenant_id'      => $tenant->id,
                'apoderado_id'   => $apoderado->id,
                'poderdante_id'  => $copropietario->id,
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
            'numero_documento' => $data['delegado_documento'] ?? null,
            'telefono'         => $data['delegado_telefono'] ?? null,
            'empresa'          => $data['delegado_empresa'] ?? null,
            'es_externo'       => true,
            'es_residente'     => false,
            'activo'           => true,
        ]);
    }
}
