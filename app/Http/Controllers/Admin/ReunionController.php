<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Services\ConvocatoriaService;
use App\Services\QuorumService;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReunionController extends Controller
{
    public function __construct(
        private ConvocatoriaService $convocatoriaService,
        private QuorumService $quorumService,
        private ReporteService $reporteService
    ) {}

    public function index()
    {
        $reuniones = Reunion::withoutGlobalScopes()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Reuniones/Index', compact('reuniones'));
    }

    public function create()
    {
        return Inertia::render('Admin/Reuniones/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'tipo' => 'required|in:asamblea,consejo,extraordinaria',
            'tipo_voto_peso' => 'required|in:coeficiente,unidad',
            'quorum_requerido' => 'required|numeric|min:1|max:100',
            'fecha_programada' => 'nullable|date',
        ]);

        $reunion = Reunion::create([...$data, 'creado_por' => auth()->id()]);

        return redirect()->route('admin.reuniones.show', $reunion)->with('success', 'Reunión creada.');
    }

    public function show(Reunion $reunion)
    {
        $quorum = $this->quorumService->calcular($reunion);
        $asistencias = $reunion->asistencias()->where('confirmada_por_admin', true)->pluck('copropietario_id')->toArray();
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->with('user', 'unidad')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));

        return Inertia::render('Admin/Reuniones/Show', compact('reunion', 'quorum', 'copropietarios'));
    }

    public function conducir(Reunion $reunion)
    {
        $quorum = $this->quorumService->calcular($reunion);
        $asistencias = $reunion->asistencias()->where('confirmada_por_admin', true)->pluck('copropietario_id')->toArray();
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->with('user', 'unidad')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));
        $votaciones = $reunion->votaciones()->with('opciones')->get();

        return Inertia::render('Admin/Reuniones/Conducir', compact('reunion', 'quorum', 'copropietarios', 'votaciones'));
    }

    public function edit(Reunion $reunion)
    {
        return Inertia::render('Admin/Reuniones/Edit', compact('reunion'));
    }

    public function update(Request $request, Reunion $reunion)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'fecha_programada' => 'nullable|date',
        ]);

        $reunion->update($data);

        return redirect()->route('admin.reuniones.show', $reunion)->with('success', 'Reunión actualizada.');
    }

    public function destroy(Reunion $reunion)
    {
        $reunion->delete();
        return redirect()->route('admin.reuniones.index')->with('success', 'Reunión eliminada.');
    }

    public function convocar(Reunion $reunion)
    {
        $this->convocatoriaService->enviar($reunion, auth()->user());
        return back()->with('success', 'Convocatoria enviada.');
    }

    public function iniciar(Reunion $reunion)
    {
        $reunion->transicionarA('en_curso', auth()->user());
        return back()->with('success', 'Reunión iniciada.');
    }

    public function finalizar(Reunion $reunion)
    {
        $reunion->transicionarA('finalizada', auth()->user());
        return back()->with('success', 'Reunión finalizada.');
    }

    public function confirmarAsistencia(Reunion $reunion, Copropietario $copropietario)
    {
        \App\Models\Asistencia::updateOrCreate(
            ['reunion_id' => $reunion->id, 'copropietario_id' => $copropietario->id],
            ['confirmada_por_admin' => true, 'hora_confirmacion' => now()]
        );

        broadcast(new \App\Events\QuorumActualizado(
            $reunion->id,
            app(QuorumService::class)->calcular($reunion)
        ));

        return back()->with('success', 'Asistencia confirmada.');
    }

    public function reportePdf(Reunion $reunion)
    {
        $pdf = $this->reporteService->generarPdf($reunion);
        return $pdf->download("acta-{$reunion->id}.pdf");
    }

    public function reporteCsv(Reunion $reunion)
    {
        $csv = $this->reporteService->generarCsvAsistencia($reunion);
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=asistencia-{$reunion->id}.csv",
        ]);
    }

    public function auditoria(Reunion $reunion)
    {
        $logs = $reunion->logs()->with('user')->orderBy('created_at')->get();
        return Inertia::render('Admin/Reuniones/Auditoria', compact('reunion', 'logs'));
    }
}
