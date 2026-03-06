<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Reunion;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $tenant = app('current_tenant');

        $stats = [
            'copropietarios' => Copropietario::count(),
            'reuniones_total' => Reunion::count(),
            'reuniones_activas' => Reunion::where('estado', 'en_curso')->count(),
        ];

        return Inertia::render('Admin/Dashboard', [
            'tenant' => $tenant,
            'stats'  => $stats,
        ]);
    }
}
