<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poderes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('reunion_id')->nullable()->constrained('reuniones')->nullOnDelete();
            $table->foreignId('apoderado_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->foreignId('poderdante_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->string('documento_url')->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'revocado', 'expirado'])->default('pendiente');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rechazado_motivo')->nullable();
            $table->timestamp('invitacion_enviada_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poderes');
    }
};
