<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CopropietarioController extends Controller
{
    public function index()
    {
        $copropietarios = Copropietario::with(['user', 'unidades'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Copropietarios/Index', [
            'copropietarios' => $copropietarios,
        ]);
    }

    public function create()
    {
        $unidades = Unidad::whereNull('copropietario_id')->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Create', [
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        $tenant = app('current_tenant');

        DB::transaction(function () use ($data, $tenant) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['nombre'],
                'email'     => $data['email'],
                'password'  => bcrypt(Str::random(16)),
                'rol'       => 'copropietario',
            ]);

            $copropietario = Copropietario::create([
                'tenant_id'        => $tenant->id,
                'user_id'          => $user->id,
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => true,
            ]);

            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario creado exitosamente.');
    }

    public function show(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);

        return Inertia::render('Admin/Copropietarios/Show', [
            'copropietario' => $copropietario,
        ]);
    }

    public function edit(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);
        // Unidades libres + las ya asignadas a este copropietario
        $unidades = Unidad::where(function ($q) use ($copropietario) {
            $q->whereNull('copropietario_id')
              ->orWhere('copropietario_id', $copropietario->id);
        })->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Edit', [
            'copropietario' => $copropietario,
            'unidades'      => $unidades,
        ]);
    }

    public function update(Request $request, Copropietario $copropietario)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $copropietario->user_id,
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'activo'          => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        DB::transaction(function () use ($data, $copropietario) {
            $copropietario->user->update([
                'name'  => $data['nombre'],
                'email' => $data['email'],
            ]);

            $copropietario->update([
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => $data['activo'] ?? true,
            ]);

            // Desasignar todas sus unidades, reasignar las enviadas
            Unidad::where('copropietario_id', $copropietario->id)->update(['copropietario_id' => null]);
            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        return redirect()->route('admin.copropietarios.show', $copropietario)
            ->with('success', 'Copropietario actualizado.');
    }

    public function destroy(Copropietario $copropietario)
    {
        $user = $copropietario->user;
        $copropietario->delete(); // nullOnDelete liberará sus unidades
        $user?->delete();

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario eliminado.');
    }
}
