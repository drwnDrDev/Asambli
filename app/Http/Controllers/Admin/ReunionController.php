<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReunionEstado;
use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Reunion;
use App\Services\ConvocatoriaService;
use App\Services\QuorumService;
use App\Services\ReporteService;
use App\Services\ReunionTransicionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ReunionController extends Controller
{
    public function __construct(
        private ConvocatoriaService $convocatoriaService,
        private QuorumService $quorumService,
        private ReporteService $reporteService,
        private ReunionTransicionService $transicionService
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
            ->with('user', 'unidades')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));
        $votaciones = $reunion->votaciones()->with('opciones')->get();

        return Inertia::render('Admin/Reuniones/Show', compact('reunion', 'quorum', 'copropietarios', 'votaciones'));
    }

    public function conducir(Reunion $reunion)
    {
        $quorum = $this->quorumService->calcular($reunion);
        $asistencias = $reunion->asistencias()->where('confirmada_por_admin', true)->pluck('copropietario_id')->toArray();
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->with('user', 'unidades')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), ['asistencia' => in_array($c->id, $asistencias)]));
        $votaciones = $reunion->votaciones()->with('opciones')->get();

        // Resultados para votaciones abiertas Y cerradas
        $resultadosIniciales = [];
        foreach ($votaciones->whereIn('estado', ['abierta', 'cerrada']) as $votacion) {
            $resultadosIniciales[$votacion->id] = $votacion->opciones->map(function ($opcion) use ($votacion) {
                $votos = \App\Models\Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacion->id)
                    ->where('opcion_id', $opcion->id);
                return [
                    'opcion_id'  => $opcion->id,
                    'texto'      => $opcion->texto,
                    'count'      => $votos->count(),
                    'peso_total' => (float) $votos->sum('peso'),
                ];
            })->toArray();
        }

        return Inertia::render('Admin/Reuniones/Conducir', compact('reunion', 'quorum', 'copropietarios', 'votaciones', 'resultadosIniciales'));
    }

    public function actualizarQuorumPresencia(Request $request, Reunion $reunion)
    {
        $request->validate([
            'coef_presente'        => 'required|numeric|min:0',
            'copropietarios_count' => 'required|integer|min:0',
        ]);

        if ($reunion->tipo_voto_peso === 'coeficiente') {
            $total    = (float) \App\Models\Unidad::withoutGlobalScopes()
                ->where('tenant_id', $reunion->tenant_id)->where('activo', true)->sum('coeficiente');
            $presente = (float) $request->coef_presente;
        } else {
            $total    = (float) \App\Models\Copropietario::withoutGlobalScopes()
                ->where('tenant_id', $reunion->tenant_id)->where('activo', true)->count();
            $presente = (float) $request->copropietarios_count;
        }

        $pct = $total > 0 ? round(($presente / $total) * 100, 2) : 0;

        $quorumData = [
            'tipo'                => $reunion->tipo_voto_peso,
            'total'               => $total,
            'presente'            => $presente,
            'porcentaje_presente' => $pct,
            'quorum_requerido'    => (float) $reunion->quorum_requerido,
            'tiene_quorum'        => $pct >= $reunion->quorum_requerido,
        ];

        broadcast(new \App\Events\QuorumActualizado($reunion->id, $quorumData));

        return response()->json($quorumData);
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
        if ($reunion->estado !== ReunionEstado::Borrador) {
            return back()->withErrors(['error' => 'Solo se puede eliminar una reunión en estado borrador.']);
        }
        $reunion->delete();
        return redirect()->route('admin.reuniones.index')->with('success', 'Reunión eliminada.');
    }

    public function convocar(Reunion $reunion)
    {
        $this->convocatoriaService->enviar($reunion, auth()->user());
        return back()->with('success', 'Convocatoria enviada.');
    }

    public function abrirAnteSala(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::AnteSala, auth()->user(), $request->observacion
        );
        return redirect()->route('admin.reuniones.conducir', $reunion)->with('success', 'Ante-sala abierta.');
    }

    public function iniciar(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::EnCurso, auth()->user(), $request->observacion
        );
        return back()->with('success', 'Reunión iniciada.');
    }

    public function finalizar(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::Finalizada, auth()->user(), $request->observacion
        );

        // Expirar todos los poderes activos del tenant al finalizar la reunión
        \App\Models\Poder::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->update(['estado' => 'expirado', 'reunion_id' => $reunion->id]);

        return back()->with('success', 'Reunión finalizada.');
    }

    public function suspender(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::Suspendida, auth()->user(), $request->observacion
        );
        return back()->with('success', 'Reunión suspendida.');
    }

    public function reactivar(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::EnCurso, auth()->user(), $request->observacion
        );
        return back()->with('success', 'Reunión reactivada.');
    }

    public function reprogramar(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::Reprogramada, auth()->user(), $request->observacion
        );
        return back()->with('success', 'Reunión reprogramada.');
    }

    public function cancelar(Request $request, Reunion $reunion)
    {
        $request->validate(['observacion' => 'required|string|min:3']);
        $this->transicionService->transicionar(
            $reunion, ReunionEstado::Cancelada, auth()->user(), $request->observacion
        );
        return back()->with('success', 'Reunión cancelada.');
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

    public function reporteCsvVotos(Reunion $reunion)
    {
        $csv = $this->reporteService->generarCsvVotos($reunion);
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=votos-{$reunion->id}.csv",
        ]);
    }

    public function auditoria(Reunion $reunion)
    {
        $logs = $reunion->logs()->with('user')->orderBy('created_at')->get();
        return Inertia::render('Admin/Reuniones/Auditoria', compact('reunion', 'logs'));
    }

    public function generarQr(Reunion $reunion)
    {
        $reunion->update([
            'qr_token'     => Str::random(64),
            'qr_expires_at' => now()->addHours(72),
        ]);

        return back()->with('success', 'QR generado. Válido por 72 horas.');
    }

    public function proyeccion(Reunion $reunion)
    {
        $votacionActiva = $reunion->votaciones()
            ->where('estado', 'abierta')
            ->with('opciones')
            ->first();

        $resultados = [];
        if ($votacionActiva) {
            $resultados = $votacionActiva->opciones->map(function ($opcion) use ($votacionActiva) {
                $votos = \App\Models\Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacionActiva->id)
                    ->where('opcion_id', $opcion->id);
                return [
                    'opcion_id'  => $opcion->id,
                    'texto'      => $opcion->texto,
                    'count'      => $votos->count(),
                    'peso_total' => (float) $votos->sum('peso'),
                ];
            })->toArray();
        }

        return Inertia::render('Admin/Reuniones/Proyeccion', [
            'reunion'    => $reunion->only('id', 'titulo', 'quorum_requerido'),
            'votacion'   => $votacionActiva,
            'resultados' => $resultados,
        ]);
    }

    public function enviarAviso(Request $request, Reunion $reunion)
    {
        $request->validate(['mensaje' => 'required|string|max:300']);
        $ts = now()->toIso8601String();

        $reunion->logs()->create([
            'user_id'  => auth()->id(),
            'accion'   => 'aviso_enviado',
            'metadata' => ['mensaje' => $request->mensaje],
        ]);

        broadcast(new \App\Events\AvisoEnviado($reunion->id, $request->mensaje, $ts));
        return back()->with('success', 'Aviso enviado.');
    }
}
