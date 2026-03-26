<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withoutGlobalScopes()->orderBy('nombre')->get();
        return Inertia::render('SuperAdmin/Tenants/Index', compact('tenants'));
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Tenants/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'nit'                     => 'required|string|max:50|unique:tenants',
            'direccion'               => 'nullable|string|max:255',
            'ciudad'                  => 'nullable|string|max:100',
            'max_poderes_por_delegado' => 'integer|min:1|max:10',
            'admin_nombre'            => 'nullable|required_with:admin_email|string|max:255',
            'admin_email'             => 'nullable|email|unique:users,email',
            'admin_password'          => 'nullable|required_with:admin_email|string|min:8',
        ]);

        $tenant = null;

        DB::transaction(function () use ($data, &$tenant) {
            $tenant = Tenant::create([
                'nombre'                  => $data['nombre'],
                'nit'                     => $data['nit'],
                'direccion'               => $data['direccion'] ?? null,
                'ciudad'                  => $data['ciudad'] ?? null,
                'max_poderes_por_delegado' => $data['max_poderes_por_delegado'] ?? 2,
            ]);

            if (!empty($data['admin_email'])) {
                User::create([
                    'tenant_id' => $tenant->id,
                    'name'      => $data['admin_nombre'],
                    'email'     => $data['admin_email'],
                    'password'  => $data['admin_password'],
                    'rol'       => 'administrador',
                    'activo'    => true,
                ]);
            }
        });

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Conjunto creado.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load([
            'users' => fn ($q) => $q->where('rol', 'administrador')->orderBy('name'),
        ]);

        $stats = [
            'reuniones'      => $tenant->reuniones()->count(),
            'copropietarios' => Copropietario::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('es_externo', false)
                ->count(),
        ];

        return Inertia::render('SuperAdmin/Tenants/Show', compact('tenant', 'stats'));
    }

    public function edit(Tenant $tenant)
    {
        return Inertia::render('SuperAdmin/Tenants/Edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'direccion'               => 'nullable|string|max:255',
            'ciudad'                  => 'nullable|string|max:100',
            'max_poderes_por_delegado' => 'integer|min:1|max:10',
            'activo'                  => 'boolean',
        ]);

        $tenant->update($data);

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Conjunto actualizado.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->update(['activo' => false]);
        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Conjunto desactivado.');
    }

    // --- Gestión de usuarios del tenant ---

    public function storeAdmin(Request $request, Tenant $tenant)
    {
        abort_if(!$tenant->activo, 422, 'No se pueden agregar admins a un conjunto desactivado.');

        $data = $request->validate([
            'nombre'   => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name'      => $data['nombre'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'rol'       => 'administrador',
            'activo'    => true,
        ]);

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', 'Administrador creado.');
    }

    public function toggleUser(Tenant $tenant, User $user)
    {
        abort_if($user->rol === 'super_admin', 422, 'No se puede modificar un super admin.');
        abort_if($user->tenant_id !== $tenant->id, 404);

        $user->update(['activo' => !$user->activo]);
        // After update(), $user->activo reflects the NEW value
        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', $user->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }
}
