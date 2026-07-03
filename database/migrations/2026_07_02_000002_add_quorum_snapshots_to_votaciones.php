<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->json('quorum_apertura')->nullable()->after('resultado');
            $table->json('quorum_cierre')->nullable()->after('quorum_apertura');
        });
    }

    public function down(): void
    {
        Schema::table('votaciones', function (Blueprint $table) {
            $table->dropColumn(['quorum_apertura', 'quorum_cierre']);
        });
    }
};
