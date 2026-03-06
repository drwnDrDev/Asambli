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
        $copropietarios = Copropietario::with(['user', 'unidad'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Copropietarios/Index', [
            'copropietarios' => $copropietarios,
        ]);
    }

    public function create()
    {
        $unidades = Unidad::orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Create', [
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'telefono'     => 'nullable|string|max:20',
            'es_residente' => 'boolean',
            'unidad_id'    => 'required|exists:unidades,id',
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

            Copropietario::create([
                'tenant_id'    => $tenant->id,
                'user_id'      => $user->id,
                'unidad_id'    => $data['unidad_id'],
                'telefono'     => $data['telefono'] ?? null,
                'es_residente' => $data['es_residente'] ?? false,
                'activo'       => true,
            ]);
        });

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario creado exitosamente.');
    }

    public function show(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidad']);

        return Inertia::render('Admin/Copropietarios/Show', [
            'copropietario' => $copropietario,
        ]);
    }

    public function edit(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidad']);
        $unidades = Unidad::orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Edit', [
            'copropietario' => $copropietario,
            'unidades'      => $unidades,
        ]);
    }

    public function update(Request $request, Copropietario $copropietario)
    {
        $data = $request->validate([
            'nombre'       => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,' . $copropietario->user_id,
            'telefono'     => 'nullable|string|max:20',
            'es_residente' => 'boolean',
            'unidad_id'    => 'required|exists:unidades,id',
            'activo'       => 'boolean',
        ]);

        DB::transaction(function () use ($data, $copropietario) {
            $copropietario->user->update([
                'name'  => $data['nombre'],
                'email' => $data['email'],
            ]);

            $copropietario->update([
                'unidad_id'    => $data['unidad_id'],
                'telefono'     => $data['telefono'] ?? null,
                'es_residente' => $data['es_residente'] ?? false,
                'activo'       => $data['activo'] ?? true,
            ]);
        });

        return redirect()->route('admin.copropietarios.show', $copropietario)
            ->with('success', 'Copropietario actualizado.');
    }

    public function destroy(Copropietario $copropietario)
    {
        $user = $copropietario->user;
        $copropietario->delete();
        $user?->delete();

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario eliminado.');
    }
}
