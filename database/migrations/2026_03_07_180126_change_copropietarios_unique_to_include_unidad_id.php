<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear el nuevo índice primero: empieza por tenant_id, el FK puede usarlo como prefijo
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->unique(['tenant_id', 'user_id', 'unidad_id']);
        });
        // Ahora es seguro borrar el viejo: el nuevo ya cubre el FK de tenant_id
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->unique(['tenant_id', 'user_id']);
        });
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id', 'unidad_id']);
        });
    }
};
