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
        Schema::create('votos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('votacion_id')->constrained('votaciones')->cascadeOnDelete();
            $table->foreignId('copropietario_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->foreignId('en_nombre_de')->nullable()->constrained('copropietarios')->nullOnDelete();
            $table->foreignId('opcion_id')->constrained('opciones_votacion');
            $table->decimal('peso', 8, 5)->default(1.00000);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('hash_verificacion', 64);
            $table->timestamp('created_at')->useCurrent();
            // Sin updated_at — votos son inmutables

            $table->unique(['votacion_id', 'copropietario_id', 'en_nombre_de']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votos');
    }
};
