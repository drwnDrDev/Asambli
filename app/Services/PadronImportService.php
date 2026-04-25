<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

class PadronImportService
{
    public function importFromFile(UploadedFile $file, Tenant $tenant): array
    {
        $rows = SimpleExcelReader::create($file->getRealPath(), $file->getClientOriginalExtension())->getRows();

        $records = collect($rows);

        $totalCoeficiente = $records->sum(fn($r) => (float) str_replace(',', '.', $r['coeficiente'] ?? 0));

        if ($totalCoeficiente > 100.001) {
            return [
                'imported' => 0,
                'errors' => ["La suma de coeficientes ({$totalCoeficiente}) supera 100."],
            ];
        }

        $imported = 0;
        $errors = [];

        DB::transaction(function () use ($records, $tenant, &$imported, &$errors) {
            foreach ($records as $index => $row) {
                $line = $index + 2;

                // Campos requeridos
                if (empty($row['numero']) || empty($row['email']) || empty($row['coeficiente'])) {
                    $errors[] = "Línea {$line}: campos requeridos faltantes (numero, email, coeficiente).";
                    continue;
                }

                if (empty($row['tipo_documento']) || empty($row['numero_documento'])) {
                    $errors[] = "Línea {$line}: tipo_documento y numero_documento son obligatorios.";
                    continue;
                }

                // Normalizar torre: null → '' para garantizar unicidad correcta
                $torre = isset($row['torre']) && $row['torre'] !== null ? (string) $row['torre'] : '';

                try {
                    $copropietario = Copropietario::withoutGlobalScopes()->updateOrCreate(
                        [
                            'tenant_id'        => $tenant->id,
                            'tipo_documento'   => $row['tipo_documento'],
                            'numero_documento' => $row['numero_documento'],
                        ],
                        [
                            'nombre'       => $row['nombre'] ?: $row['email'],
                            'email'        => $row['email'],
                            'es_residente' => isset($row['es_residente'])
                                ? filter_var($row['es_residente'], FILTER_VALIDATE_BOOLEAN)
                                : true,
                            'telefono' => $row['telefono'] ?? null,
                            'activo'   => true,
                        ]
                    );

                    Unidad::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'numero' => $row['numero'], 'torre' => $torre],
                        [
                            'copropietario_id' => $copropietario->id,
                            'tipo'             => $row['tipo'] ?? 'apartamento',
                            'coeficiente'      => (float) str_replace(',', '.', $row['coeficiente']),
                            'piso'             => $row['piso'] ?? null,
                            'activo'           => true,
                        ]
                    );

                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Línea {$line}: " . $e->getMessage();
                }
            }
        });

        return ['imported' => $imported, 'errors' => $errors];
    }
}
