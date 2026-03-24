<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poderes', function (Blueprint $table) {
            // 1. Soltar FK primero (esto también permite soltar el unique)
            $table->dropForeign(['reunion_id']);
            // 2. Soltar unique constraint
            $table->dropUnique(['reunion_id', 'poderdante_id']);
            // 3. Re-crear reunion_id nullable con nullOnDelete
            $table->unsignedBigInteger('reunion_id')->nullable()->change();
            $table->foreign('reunion_id')->references('id')->on('reuniones')->nullOnDelete();
        });

        // 4. Agregar estado 'expirado' al enum
        DB::statement("ALTER TABLE poderes MODIFY COLUMN estado ENUM('pendiente','aprobado','rechazado','revocado','expirado') NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE poderes MODIFY COLUMN estado ENUM('pendiente','aprobado','rechazado','revocado') NOT NULL DEFAULT 'pendiente'");

        Schema::table('poderes', function (Blueprint $table) {
            $table->dropForeign(['reunion_id']);
            $table->foreignId('reunion_id')
                ->nullable(false)
                ->change()
                ->constrained('reuniones')
                ->cascadeOnDelete();

            $table->unique(['reunion_id', 'poderdante_id']);
        });
    }
};
