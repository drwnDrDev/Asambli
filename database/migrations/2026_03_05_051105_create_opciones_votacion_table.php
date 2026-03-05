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
        Schema::create('opciones_votacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('votacion_id')->constrained('votaciones')->cascadeOnDelete();
            $table->string('texto');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opciones_votacion');
    }
};
