<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; }
        h1 { font-size: 16px; text-align: center; }
        h2 { font-size: 13px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #f0f0f0; padding: 6px; text-align: left; }
        td { padding: 5px; border-bottom: 1px solid #eee; }
        .footer { font-size: 9px; color: #888; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <h1>{{ $reunion->titulo }}</h1>
    <p style="text-align:center">{{ $tenant->nombre }} — {{ $tenant->nit }}</p>

    <h2>1. Información General</h2>
    <table>
        <tr><th>Tipo</th><td>{{ ucfirst($reunion->tipo) }}</td></tr>
        <tr><th>Fecha</th><td>{{ $reunion->fecha_inicio?->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Quórum requerido</th><td>{{ $reunion->quorum_requerido }}%</td></tr>
        <tr><th>Quórum alcanzado</th><td>{{ $quorum['porcentaje_presente'] }}%</td></tr>
        <tr><th>Estado</th><td>{{ ucfirst($reunion->estado) }}</td></tr>
    </table>

    <h2>2. Asistentes</h2>
    <table>
        <tr><th>Unidad</th><th>Copropietario</th><th>Coeficiente</th><th>Hora</th></tr>
        @foreach($asistentes as $a)
        <tr>
            <td>{{ $a->copropietario->unidad->numero }}</td>
            <td>{{ $a->copropietario->user->name }}</td>
            <td>{{ $a->copropietario->unidad->coeficiente }}%</td>
            <td>{{ $a->hora_confirmacion?->format('H:i') }}</td>
        </tr>
        @endforeach
    </table>

    <h2>3. Votaciones</h2>
    @foreach($votaciones as $v)
    <p><strong>{{ $v->titulo }}</strong> ({{ $v->estado }})</p>
    <table>
        <tr><th>Opción</th><th>Votos</th><th>Peso</th><th>%</th></tr>
        @php $pesoTotal = collect($v->resultados)->sum('peso_total'); @endphp
        @foreach($v->resultados as $r)
        <tr>
            <td>{{ $r['texto'] }}</td>
            <td>{{ $r['count'] }}</td>
            <td>{{ number_format($r['peso_total'], 2) }}</td>
            <td>{{ $pesoTotal > 0 ? number_format(($r['peso_total'] / $pesoTotal) * 100, 1) : 0 }}%</td>
        </tr>
        @endforeach
    </table>
    @endforeach

    <h2>4. Log de Eventos</h2>
    <table>
        <tr><th>Fecha/Hora</th><th>Acción</th></tr>
        @foreach($logs as $log)
        <tr>
            <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
            <td>{{ $log->accion }}</td>
        </tr>
        @endforeach
    </table>

    <div class="footer">
        Hash del documento: {{ $hash }}<br>
        Generado por ASAMBLI el {{ now()->format('d/m/Y H:i:s') }}<br>
        <strong>Pendiente de firma por el Administrador</strong>
    </div>
</body>
</html>
