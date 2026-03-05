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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('nit')->unique();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('logo_url')->nullable();
            $table->unsignedTinyInteger('max_poderes_por_delegado')->default(2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
