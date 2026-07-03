<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Reestructurar tipo de reunión
        Schema::table('reuniones', function (Blueprint $table) {
            $table->enum('tipo_cuerpo', ['asamblea', 'consejo'])
                  ->default('asamblea')->after('titulo');
            $table->enum('tipo_convocatoria', ['ordinaria', 'extraordinaria'])
                  ->default('ordinaria')->after('tipo_cuerpo');
        });

        DB::statement("UPDATE reuniones SET tipo_cuerpo='asamblea',  tipo_convocatoria='ordinaria'      WHERE tipo='asamblea'");
        DB::statement("UPDATE reuniones SET tipo_cuerpo='asamblea',  tipo_convocatoria='extraordinaria' WHERE tipo='extraordinaria'");
        DB::statement("UPDATE reuniones SET tipo_cuerpo='consejo',   tipo_convocatoria='ordinaria'      WHERE tipo='consejo'");

        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        // 2. Catálogo legal de tipos de decisión
        Schema::create('tipos_decision', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion');
            $table->enum('tipo_mayoria', ['simple', 'calificada_70', 'unanimidad']);
            $table->json('aplica_en');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        // 3. FK en votaciones + campo resultado
        Schema::table('votaciones', function (Blueprint $table) {
            $table->foreignId('tipo_decision_id')->nullable()
                  ->constrained('tipos_decision')->nullOnDelete()
                  ->after('descripcion');
            $table->enum('resultado', ['pendiente', 'aprobada', 'rechazada'])
                  ->default('pendiente')->after('tipo_decision_id');
        });

        // 4. Estado de mora en copropietarios
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->boolean('en_mora')->default(false)->after('activo');
        });

        // 5. Configuración de mora en tenants
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('restringir_voto_morosos')->default(true)->after('max_poderes_por_delegado');
        });
    }

    public function down(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->dropForeign(['tipo_decision_id']);
            $table->dropColumn(['tipo_decision_id', 'resultado']);
        });

        Schema::dropIfExists('tipos_decision');

        Schema::table('reuniones', function (Blueprint $table) {
            $table->enum('tipo', ['asamblea', 'consejo', 'extraordinaria'])
                  ->default('asamblea')->after('titulo');
        });

        DB::statement("UPDATE reuniones SET tipo='asamblea'       WHERE tipo_cuerpo='asamblea' AND tipo_convocatoria='ordinaria'");
        DB::statement("UPDATE reuniones SET tipo='extraordinaria' WHERE tipo_cuerpo='asamblea' AND tipo_convocatoria='extraordinaria'");
        DB::statement("UPDATE reuniones SET tipo='consejo'        WHERE tipo_cuerpo='consejo'");

        Schema::table('reuniones', function (Blueprint $table) {
            $table->dropColumn(['tipo_cuerpo', 'tipo_convocatoria']);
        });

        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropColumn('en_mora');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('restringir_voto_morosos');
        });
    }
};
