<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reunion_logs', function (Blueprint $table) {
            $table->text('observacion')->nullable()->after('accion');
        });
    }

    public function down(): void
    {
        Schema::table('reunion_logs', function (Blueprint $table) {
            $table->dropColumn('observacion');
        });
    }
};
