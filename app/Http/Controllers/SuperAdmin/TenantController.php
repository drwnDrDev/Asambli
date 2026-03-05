<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
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
            'nombre' => 'required|string|max:255',
            'nit' => 'required|string|max:50|unique:tenants',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'max_poderes_por_delegado' => 'integer|min:1|max:10',
        ]);

        $tenant = Tenant::create($data);

        return redirect()->route('super-admin.tenants.show', $tenant)->with('success', 'Conjunto creado.');
    }

    public function show(Tenant $tenant)
    {
        $tenant->load('users');
        return Inertia::render('SuperAdmin/Tenants/Show', compact('tenant'));
    }

    public function edit(Tenant $tenant)
    {
        return Inertia::render('SuperAdmin/Tenants/Edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            'max_poderes_por_delegado' => 'integer|min:1|max:10',
            'activo' => 'boolean',
        ]);

        $tenant->update($data);

        return redirect()->route('super-admin.tenants.show', $tenant)->with('success', 'Conjunto actualizado.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->update(['activo' => false]);
        return redirect()->route('super-admin.tenants.index')->with('success', 'Conjunto desactivado.');
    }
}
