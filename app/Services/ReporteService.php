<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Reunion;
use App\Models\Votacion;
use App\Models\Voto;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteService
{
    public function __construct(private QuorumService $quorumService) {}

    public function generarPdf(Reunion $reunion)
    {
        $tenant = \App\Models\Tenant::withoutGlobalScopes()->find($reunion->tenant_id);
        $quorum = $this->quorumService->calcular($reunion);

        $asistentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->with('copropietario.user', 'copropietario.unidades')
            ->get();

        $votaciones = Votacion::withoutGlobalScopes()
            ->where('reunion_id', $reunion->id)
            ->with('opciones')
            ->get()
            ->map(function ($v) {
                $v->resultados = $v->opciones->map(fn($o) => [
                    'texto' => $o->texto,
                    'count' => Voto::withoutGlobalScopes()->where('votacion_id', $v->id)->where('opcion_id', $o->id)->count(),
                    'peso_total' => (float) Voto::withoutGlobalScopes()->where('votacion_id', $v->id)->where('opcion_id', $o->id)->sum('peso'),
                ])->toArray();
                return $v;
            });

        $logs = $reunion->logs()->orderBy('created_at')->get();

        $hash = '';
        $contenido = view('reportes.acta', compact('reunion', 'tenant', 'quorum', 'asistentes', 'votaciones', 'logs', 'hash'))->render();
        $hash = hash('sha256', $contenido . config('app.key'));

        return Pdf::loadView('reportes.acta', compact(
            'reunion', 'tenant', 'quorum', 'asistentes', 'votaciones', 'logs', 'hash'
        ));
    }

    public function generarCsvAsistencia(Reunion $reunion): string
    {
        $rows = ["unidad,copropietario,coeficiente,hora_confirmacion\n"];

        $asistentes = Asistencia::where('reunion_id', $reunion->id)
            ->where('confirmada_por_admin', true)
            ->with('copropietario.user', 'copropietario.unidades')
            ->get();

        foreach ($asistentes as $a) {
            foreach ($a->copropietario->unidades as $unidad) {
                $rows[] = implode(',', [
                    $unidad->numero,
                    '"' . $a->copropietario->user->name . '"',
                    $unidad->coeficiente,
                    $a->hora_confirmacion?->format('d/m/Y H:i:s'),
                ]) . "\n";
            }
        }

        return implode('', $rows);
    }
}
