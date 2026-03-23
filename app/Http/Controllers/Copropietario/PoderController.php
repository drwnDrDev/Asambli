<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Reunion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PoderController extends Controller
{
    public function create(Reunion $reunion)
    {
        return Inertia::render('Copropietario/Sala/Poderes/Create', [
            'reunion' => $reunion,
        ]);
    }

    public function store(Request $request, Reunion $reunion)
    {
        $data = $request->validate([
            'delegado_nombre'  => 'required|string|max:255',
            'delegado_email'   => 'required|email',
            'delegado_telefono'=> 'nullable|string|max:30',
            'delegado_documento'=> 'nullable|string|max:50',
            'delegado_empresa' => 'nullable|string|max:150',
        ]);

        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();
        $tenant = app('current_tenant');

        // Verificar que el copropietario no tenga ya un poder activo para esta reunión
        $yaExiste = Poder::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($yaExiste) {
            return back()->withErrors(['delegado_email' => 'Ya tienes un poder registrado para esta reunión.']);
        }

        DB::transaction(function () use ($data, $reunion, $copropietario, $tenant) {
            $apoderado = $this->resolverOCrearDelegado($data, $tenant);

            Poder::create([
                'tenant_id'    => $tenant->id,
                'reunion_id'   => $reunion->id,
                'apoderado_id' => $apoderado->id,
                'poderdante_id'=> $copropietario->id,
                'registrado_por' => auth()->id(),
                'estado'       => 'pendiente',
            ]);
        });

        return redirect()->route('sala.index')
            ->with('success', 'Solicitud de poder enviada. El administrador la revisará pronto.');
    }

    public function destroy(Reunion $reunion, Poder $poder)
    {
        $copropietario = Copropietario::where('user_id', auth()->id())->firstOrFail();

        abort_if($poder->poderdante_id !== $copropietario->id, 403);
        abort_if($poder->estado !== 'pendiente', 422, 'Solo puedes retirar poderes pendientes de aprobación.');

        $poder->update(['estado' => 'rechazado', 'rechazado_motivo' => 'Retirado por el copropietario']);

        return back()->with('success', 'Solicitud de poder retirada.');
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
