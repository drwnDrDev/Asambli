<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencia_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('copropietario_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->enum('tipo', ['entrada', 'salida']);
            $table->enum('origen', ['auto_sala', 'admin', 'representado']);
            $table->decimal('quorum_resultante', 8, 4)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reunion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencia_eventos');
    }
};
