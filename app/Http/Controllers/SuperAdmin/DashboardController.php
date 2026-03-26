<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reunion;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'tenants_activos'   => Tenant::withoutGlobalScopes()->where('activo', true)->count(),
                'tenants_inactivos' => Tenant::withoutGlobalScopes()->where('activo', false)->count(),
                'reuniones_activas' => Reunion::withoutGlobalScopes()
                    ->whereIn('estado', ['ante_sala', 'en_curso', 'suspendida'])
                    ->count(),
                'total_usuarios'    => User::withoutGlobalScopes()
                    ->where('rol', '!=', 'super_admin')
                    ->count(),
            ],
            'tenants_recientes' => Tenant::withoutGlobalScopes()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'nombre', 'ciudad', 'activo', 'created_at']),
        ]);
    }
}
