<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reunion;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReunionController extends Controller
{
    public function create(Tenant $tenant)
    {
        return Inertia::render('SuperAdmin/Reuniones/Create', [
            'tenant' => $tenant,
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'titulo'           => 'required|string|max:200',
            'tipo'             => 'required|in:asamblea',
            'tipo_voto_peso'   => 'required|in:coeficiente,unidad',
            'quorum_requerido' => 'required|numeric|min:0|max:100',
            'fecha_programada' => 'nullable|date',
        ]);

        Reunion::create([
            ...$validated,
            'tenant_id'  => $tenant->id,
            'estado'     => \App\Enums\ReunionEstado::Borrador,
            'modalidad'  => 'presencial',
            'creado_por' => auth()->id(),
        ]);

        return redirect("/super-admin/tenants/{$tenant->id}")
            ->with('success', 'Reunión creada exitosamente.');
    }

    public function resetConvocatoria(Reunion $reunion)
    {
        abort_if(
            in_array($reunion->estado, [
                \App\Enums\ReunionEstado::EnCurso,
                \App\Enums\ReunionEstado::Finalizada,
                \App\Enums\ReunionEstado::Cancelada,
            ]),
            422,
            'No se puede resetear la convocatoria en este estado.'
        );

        $reunion->update(['convocatoria_envios' => 0]);
        return back()->with('success', 'Contador de convocatorias reseteado.');
    }
}
