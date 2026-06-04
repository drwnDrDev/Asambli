<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->enum('producto', ['presencial', 'virtual', 'ambos'])->default('presencial');
            $table->timestamps();
        });

        // FK de users → tenants (tabla users ya existe al correr esta migración)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('remember_token')
                ->constrained('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
        Schema::dropIfExists('tenants');
    }
};
