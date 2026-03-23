<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->boolean('es_externo')->default(false)->after('es_residente');
            $table->string('empresa', 150)->nullable()->after('es_externo');
        });
    }

    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropColumn(['es_externo', 'empresa']);
        });
    }
};
