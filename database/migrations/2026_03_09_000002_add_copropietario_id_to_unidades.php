<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->foreignId('copropietario_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('copropietarios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropForeign(['copropietario_id']);
            $table->dropColumn('copropietario_id');
        });
    }
};
