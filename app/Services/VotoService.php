<?php

namespace App\Services;

use App\Jobs\RecalcularResultadosVotacion;
use App\Models\Copropietario;
use App\Models\Votacion;
use App\Models\Voto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VotoService
{
    public function votar(
        Votacion $votacion,
        Copropietario $copropietario,
        int $opcionId,
        Request $request,
        ?int $enNombreDeId = null
    ): array {
        try {
            DB::transaction(function () use ($votacion, $copropietario, $opcionId, $request, $enNombreDeId) {
                // 1. Verificar reunión en curso
                if ($votacion->reunion->estado !== \App\Enums\ReunionEstado::EnCurso) {
                    throw new \Exception('La reunión no está en curso.');
                }

                // 2. Verificar quórum
                $quorumService = app(QuorumService::class);
                $quorum = $quorumService->calcular($votacion->reunion);
                if (!$quorum['tiene_quorum'] && !config('app.bypass_quorum')) {
                    throw new \Exception('No hay quórum suficiente para votar.');
                }

                // 3. Verificar votación abierta
                if ($votacion->estado !== 'abierta') {
                    throw new \Exception('La votación no está abierta.');
                }

                // 4. Verificar no duplicado
                $existe = Voto::withoutGlobalScopes()
                    ->where('votacion_id', $votacion->id)
                    ->where('copropietario_id', $copropietario->id)
                    ->where('en_nombre_de', $enNombreDeId)
                    ->exists();

                if ($existe) {
                    throw new \Exception('Este copropietario ya votó en esta votación.');
                }

                // 5. Calcular peso
                $peso = $this->calcularPeso($votacion, $enNombreDeId ?? $copropietario->id);

                // 6. Generar hash de verificación
                $hash = hash('sha256', implode('|', [
                    $votacion->id,
                    $copropietario->id,
                    $opcionId,
                    now()->toISOString(),
                    config('app.key'),
                ]));

                // 7. Insertar voto (inmutable)
                Voto::create([
                    'tenant_id' => $votacion->tenant_id,
                    'votacion_id' => $votacion->id,
                    'copropietario_id' => $copropietario->id,
                    'en_nombre_de' => $enNombreDeId,
                    'opcion_id' => $opcionId,
                    'peso' => $peso,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'hash_verificacion' => $hash,
                    'created_at' => now(),
                ]);
            });

            // 8. Disparar job para recalcular y broadcast (fuera de la transacción)
            RecalcularResultadosVotacion::dispatch($votacion->id, $copropietario->id);

            return ['success' => true];

        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            return ['success' => false, 'error' => 'Este copropietario ya votó en esta votación.'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function calcularPeso(Votacion $votacion, int $copropietarioId): float
    {
        if ($votacion->reunion->tipo_voto_peso === 'unidad') {
            return 1.0;
        }

        $copropietario = Copropietario::withoutGlobalScopes()->with('unidades')->find($copropietarioId);
        return (float) $copropietario->unidades->sum('coeficiente');
    }
}
