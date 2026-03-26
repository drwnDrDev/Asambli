<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantSettingsController extends Controller
{
    public function edit()
    {
        return Inertia::render('Admin/Configuracion', [
            'tenant' => app('current_tenant'),
        ]);
    }

    public function update(Request $request)
    {
        $tenant = app('current_tenant');

        $data = $request->validate([
            'nombre'                  => 'required|string|max:255',
            'direccion'               => 'nullable|string|max:255',
            'ciudad'                  => 'nullable|string|max:100',
            'max_poderes_por_delegado' => 'required|integer|min:1|max:10',
        ]);

        $tenant->update($data);

        return redirect()->route('admin.configuracion')
            ->with('success', 'Configuración actualizada.');
    }
}
