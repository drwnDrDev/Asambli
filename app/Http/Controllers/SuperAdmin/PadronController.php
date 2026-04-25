<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Services\PadronImportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PadronController extends Controller
{
    public function __construct(private PadronImportService $importService) {}

    public function index(Tenant $tenant)
    {
        $totalCopropietarios = Copropietario::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('es_externo', false)->count();
        $totalUnidades       = Unidad::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
        $totalCoeficiente    = Unidad::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sum('coeficiente');
        $ultimaImportacion   = Copropietario::withoutGlobalScopes()->where('tenant_id', $tenant->id)->max('updated_at');

        return Inertia::render('SuperAdmin/Tenants/Padron', [
            'tenant' => $tenant,
            'resumen' => $totalCopropietarios > 0 ? [
                'copropietarios'    => $totalCopropietarios,
                'unidades'          => $totalUnidades,
                'totalCoeficiente'  => round((float) $totalCoeficiente, 5),
                'ultimaImportacion' => $ultimaImportacion,
            ] : null,
        ]);
    }

    public function import(Request $request, Tenant $tenant)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $result = $this->importService->importFromFile($request->file('archivo'), $tenant);

        if (!empty($result['errors'])) {
            return back()->withErrors(['archivo' => implode(' | ', $result['errors'])]);
        }

        return redirect()->route('super-admin.tenants.show', $tenant)
            ->with('success', "Importación completada: {$result['imported']} copropietarios procesados.");
    }
}
