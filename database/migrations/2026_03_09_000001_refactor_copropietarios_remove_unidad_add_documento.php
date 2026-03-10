<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM copropietarios'))
            ->pluck('Key_name')->unique()->values()->toArray();
        $columns = collect(\Illuminate\Support\Facades\DB::select('DESCRIBE copropietarios'))
            ->pluck('Field')->toArray();

        // Step 1: Ensure a standalone tenant_id index exists (needed so MySQL allows dropping the composite one)
        if (in_array('copropietarios_tenant_id_user_id_unidad_id_unique', $indexes)
            && !in_array('copropietarios_tenant_id_index', $indexes)) {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE copropietarios ADD INDEX `copropietarios_tenant_id_index` (`tenant_id`)'
            );
        }

        // Step 2: Drop composite unique index if still present
        if (in_array('copropietarios_tenant_id_user_id_unidad_id_unique', $indexes)) {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE copropietarios DROP INDEX `copropietarios_tenant_id_user_id_unidad_id_unique`'
            );
        }

        // Step 3: Drop unidad_id FK (if the constraint still exists) then the column
        if (in_array('unidad_id', $columns)) {
            $fks = collect(\Illuminate\Support\Facades\DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'copropietarios'
                 AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'copropietarios_unidad_id_foreign'"
            ))->count();
            if ($fks > 0) {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE copropietarios DROP FOREIGN KEY `copropietarios_unidad_id_foreign`'
                );
            }
            // Drop the orphaned index if still present
            $idxNow = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM copropietarios'))
                ->pluck('Key_name')->unique()->values()->toArray();
            if (in_array('copropietarios_unidad_id_foreign', $idxNow)) {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE copropietarios DROP INDEX `copropietarios_unidad_id_foreign`'
                );
            }
            Schema::table('copropietarios', function (Blueprint $table) {
                $table->dropColumn('unidad_id');
            });
        }

        // Step 4: Add document fields if not present
        $currentColumns = collect(\Illuminate\Support\Facades\DB::select('DESCRIBE copropietarios'))
            ->pluck('Field')->toArray();
        Schema::table('copropietarios', function (Blueprint $table) use ($currentColumns) {
            if (!in_array('tipo_documento', $currentColumns)) {
                $table->string('tipo_documento', 10)->nullable()->after('user_id');
            }
            if (!in_array('numero_documento', $currentColumns)) {
                $table->string('numero_documento', 30)->nullable()->after('tipo_documento');
            }
        });

        // Step 5: Add new unique constraints if not present
        $freshIndexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM copropietarios'))
            ->pluck('Key_name')->unique()->values()->toArray();
        Schema::table('copropietarios', function (Blueprint $table) use ($freshIndexes) {
            if (!in_array('copropietarios_tenant_id_user_id_unique', $freshIndexes)) {
                $table->unique(['tenant_id', 'user_id']);
            }
            if (!in_array('copropietarios_tenant_id_tipo_documento_numero_documento_unique', $freshIndexes)) {
                $table->unique(['tenant_id', 'tipo_documento', 'numero_documento']);
            }
        });

        // Step 6: Remove the temporary standalone tenant_id index if it was created
        $finalIndexes = collect(\Illuminate\Support\Facades\DB::select('SHOW INDEX FROM copropietarios'))
            ->pluck('Key_name')->unique()->values()->toArray();
        if (in_array('copropietarios_tenant_id_index', $finalIndexes)) {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE copropietarios DROP INDEX `copropietarios_tenant_id_index`'
            );
        }
    }

    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'tipo_documento', 'numero_documento']);
            $table->dropUnique(['tenant_id', 'user_id']);
            $table->dropColumn(['tipo_documento', 'numero_documento']);
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id', 'unidad_id']);
        });
    }
};
