<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE reuniones MODIFY COLUMN estado ENUM('borrador','ante_sala','en_curso','suspendida','finalizada','cancelada','reprogramada') NOT NULL DEFAULT 'borrador'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE reuniones MODIFY COLUMN estado ENUM('borrador','convocada','en_curso','finalizada') NOT NULL DEFAULT 'borrador'");
    }
};
