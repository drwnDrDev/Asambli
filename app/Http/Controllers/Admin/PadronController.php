<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PadronImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PadronController extends Controller
{
    public function __construct(private PadronImportService $importService) {}

    public function index()
    {
        return Inertia::render('Admin/Padron/Index');
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $tenant = app('current_tenant');
        $csv = file_get_contents($request->file('archivo')->getRealPath());

        $result = $this->importService->importFromString($csv, $tenant);

        if (!$result['success']) {
            return back()->withErrors(['archivo' => $result['error']]);
        }

        return back()->with('success', "Padrón importado: {$result['creados']} creados, {$result['actualizados']} actualizados.");
    }
}
