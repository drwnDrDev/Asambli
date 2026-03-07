<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Unidad;
use App\Services\PadronImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PadronController extends Controller
{
    public function __construct(private PadronImportService $importService) {}

    public function index()
    {
        $totalCopropietarios = Copropietario::count();
        $totalUnidades       = Unidad::count();
        $totalCoeficiente    = Unidad::sum('coeficiente');
        $ultimaImportacion   = Copropietario::max('updated_at');

        return Inertia::render('Admin/Padron/Index', [
            'resumen' => $totalCopropietarios > 0 ? [
                'copropietarios'   => $totalCopropietarios,
                'unidades'         => $totalUnidades,
                'totalCoeficiente' => round((float) $totalCoeficiente, 5),
                'ultimaImportacion' => $ultimaImportacion,
            ] : null,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $tenant = app('current_tenant');

        $result = $this->importService->importFromFile($request->file('archivo'), $tenant);

        if (!empty($result['errors'])) {
            return back()->withErrors(['archivo' => implode(' | ', $result['errors'])]);
        }

        return redirect()->route('admin.copropietarios.index')
            ->with('success', "Importación completada: {$result['imported']} copropietarios procesados.");
    }
}
