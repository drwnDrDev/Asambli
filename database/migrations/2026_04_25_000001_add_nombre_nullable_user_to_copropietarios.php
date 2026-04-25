<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            // Drop unique constraint y FK antes de hacer nullable
            $table->dropUnique('copropietarios_tenant_id_user_id_unique');
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('nombre')->nullable()->after('user_id');
        });

        // Migrar datos: copiar users.name → copropietarios.nombre
        DB::statement("
            UPDATE copropietarios c
            JOIN users u ON c.user_id = u.id
            SET c.nombre = u.name
            WHERE c.nombre IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropColumn('nombre');
            $table->dropForeign(['user_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['tenant_id', 'user_id']);
        });
    }
};
