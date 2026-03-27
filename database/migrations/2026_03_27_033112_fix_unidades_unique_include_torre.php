<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Normalizar torre NULL → '' para que el unique funcione correctamente en MySQL
        DB::statement("UPDATE unidades SET torre = '' WHERE torre IS NULL");

        Schema::table('unidades', function (Blueprint $table) {
            $table->string('torre')->default('')->nullable(false)->change();
            $table->dropUnique(['tenant_id', 'numero']);
            $table->unique(['tenant_id', 'numero', 'torre']);
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'numero', 'torre']);
            $table->unique(['tenant_id', 'numero']);
            $table->string('torre')->nullable()->default(null)->change();
        });
    }
};
