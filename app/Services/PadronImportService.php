<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use League\Csv\Reader;

class PadronImportService
{
    public function importFromString(string $csvContent, Tenant $tenant): array
    {
        $csv = Reader::createFromString($csvContent);
        $csv->setHeaderOffset(0);

        $records = collect($csv->getRecords());
        $totalCoeficiente = $records->sum('coeficiente');

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
                            'name' => $row['nombre'] ?? $row['email'],
                            'password' => bcrypt(Str::random(16)),
                            'rol' => 'copropietario',
                        ]
                    );

                    $unidad = Unidad::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'numero' => $row['numero']],
                        [
                            'tipo' => $row['tipo'] ?? 'apartamento',
                            'coeficiente' => $row['coeficiente'],
                            'torre' => $row['torre'] ?? null,
                            'piso' => $row['piso'] ?? null,
                        ]
                    );

                    Copropietario::withoutGlobalScopes()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                        ['unidad_id' => $unidad->id, 'activo' => true]
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
