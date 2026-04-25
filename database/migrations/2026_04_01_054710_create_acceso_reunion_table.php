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
        Schema::create('acceso_reunion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('copropietario_id')->constrained('copropietarios')->cascadeOnDelete();
            $table->foreignId('reunion_id')->constrained('reuniones')->cascadeOnDelete();
            $table->string('pin_hash', 64);
            $table->string('pin_plain', 6)->nullable(); // PIN en claro — se nullifica al cerrar reunión
            $table->string('session_token', 64)->nullable()->unique();
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['copropietario_id', 'reunion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acceso_reunion');
    }
};
