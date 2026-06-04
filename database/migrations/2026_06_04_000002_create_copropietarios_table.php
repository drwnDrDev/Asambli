<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copropietarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre')->nullable();
            $table->string('tipo_documento', 10)->nullable();
            $table->string('numero_documento', 30)->nullable();
            $table->boolean('es_residente')->default(true);
            $table->boolean('es_externo')->default(false);
            $table->string('empresa', 150)->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo_documento', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copropietarios');
    }
};
