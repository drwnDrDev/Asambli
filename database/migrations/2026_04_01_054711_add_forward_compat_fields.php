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
        Schema::table('reuniones', function (Blueprint $table) {
            $table->enum('modalidad', ['presencial', 'virtual'])->default('presencial')->after('tipo');
            $table->tinyInteger('convocatoria_envios')->default(0)->after('convocatoria_enviada_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('producto', ['presencial', 'virtual', 'ambos'])->default('presencial')->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn(['modalidad', 'convocatoria_envios']);
        });
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('producto');
        });
    }
};
