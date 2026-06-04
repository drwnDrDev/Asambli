<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('copropietario_id')->nullable()->constrained('copropietarios')->nullOnDelete();
            $table->string('numero');
            $table->enum('tipo', ['apartamento', 'local', 'parqueadero', 'otro'])->default('apartamento');
            $table->decimal('coeficiente', 8, 5);
            $table->string('torre')->default('');
            $table->string('piso')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'numero', 'torre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
