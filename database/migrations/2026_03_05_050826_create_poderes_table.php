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
        Schema::create('poderes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->foreignId('apoderado_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->foreignId('poderdante_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->string('documento_url')->nullable();
            $table->foreignId('registrado_por')->constrained('users');
            $table->timestamps();

            $table->unique(['reunion_id', 'poderdante_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poderes');
    }
};
