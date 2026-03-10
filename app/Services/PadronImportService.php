<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

                if (empty($row['numero']) || empty($row['email']) || empty($row['coeficiente'])) {
                    $errors[] = "Línea {$line}: campos requeridos faltantes (numero, email, coeficiente).";
                    continue;
                }

                try {
                    $user = User::withoutGlobalScopes()->firstOrCreate(
                        ['email' => $row['email']],
                        [
                            'tenant_id' => $tenant->id,
                            'name'      => $row['nombre'] ?: $row['email'],
                            'password'  => Str::random(16),
                            'rol'       => 'copropietario',
                        ]
                    );

                    $coproData = [
                        'es_residente' => isset($row['es_residente'])
                            ? filter_var($row['es_residente'], FILTER_VALIDATE_BOOLEAN)
                            : true,
                        'telefono' => $row['telefono'] ?? null,
                        'activo'   => true,
                    ];

                    if (!empty($row['tipo_documento'])) {
                        $coproData['tipo_documento']   = $row['tipo_documento'];
                        $coproData['numero_documento'] = $row['numero_documento'] ?? null;
                    }

                    $copropietario = Copropietario::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        $coproData
                    );

                    Unidad::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'numero' => $row['numero']],
                        [
                            'copropietario_id' => $copropietario->id,
                            'tipo'             => $row['tipo'] ?? 'apartamento',
                            'coeficiente'      => (float) str_replace(',', '.', $row['coeficiente']),
                            'torre'            => $row['torre'] ?? null,
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
