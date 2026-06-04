<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->string('pregunta');
            $table->text('descripcion')->nullable();
            $table->enum('tipo', ['si_no', 'si_no_abstencion', 'opcion_multiple'])->default('si_no');
            $table->boolean('es_secreta')->default(true);
            $table->enum('estado', ['creada', 'abierta', 'cerrada', 'pausada'])->default('creada');
            $table->timestamp('abierta_at')->nullable();
            $table->timestamp('cerrada_at')->nullable();
            $table->foreignId('creada_por')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votaciones');
    }
};
