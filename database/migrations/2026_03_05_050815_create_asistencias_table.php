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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('copropietario_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->boolean('confirmada_por_admin')->default(false);
            $table->timestamp('hora_confirmacion')->nullable();
            $table->json('vota_por_poderes')->nullable();
            $table->timestamps();

            $table->unique(['reunion_id', 'copropietario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
