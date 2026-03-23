<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poderes', function (Blueprint $table) {
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'revocado'])
                ->default('pendiente')
                ->after('registrado_por');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete()->after('estado');
            $table->text('rechazado_motivo')->nullable()->after('aprobado_por');
            $table->timestamp('invitacion_enviada_at')->nullable()->after('rechazado_motivo');
        });
    }

    public function down(): void
    {
        Schema::table('poderes', function (Blueprint $table) {
            $table->dropForeign(['aprobado_por']);
            $table->dropColumn(['estado', 'aprobado_por', 'rechazado_motivo', 'invitacion_enviada_at']);
        });
    }
};
