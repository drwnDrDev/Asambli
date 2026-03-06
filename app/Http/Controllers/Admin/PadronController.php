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

        if (!empty($result['errors'])) {
            return back()->withErrors(['archivo' => implode(' | ', $result['errors'])]);
        }

        return back()->with('success', "Importación completada: {$result['imported']} copropietarios procesados.");
    }
}
