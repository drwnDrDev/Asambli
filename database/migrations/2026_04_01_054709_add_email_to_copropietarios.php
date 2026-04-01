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
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->string('email')->nullable()->after('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('copropietarios', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
