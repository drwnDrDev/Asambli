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
        Schema::create('reuniones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('titulo');
            $table->enum('tipo', ['asamblea', 'consejo', 'extraordinaria'])->default('asamblea');
            $table->enum('tipo_voto_peso', ['coeficiente', 'unidad'])->default('coeficiente');
            $table->decimal('quorum_requerido', 5, 2)->default(50.00);
            $table->enum('estado', ['borrador', 'convocada', 'en_curso', 'finalizada'])->default('borrador');
            $table->timestamp('fecha_programada')->nullable();
            $table->timestamp('fecha_inicio')->nullable();
            $table->timestamp('fecha_fin')->nullable();
            $table->timestamp('convocatoria_enviada_at')->nullable();
            $table->foreignId('creado_por')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reuniones');
    }
};
