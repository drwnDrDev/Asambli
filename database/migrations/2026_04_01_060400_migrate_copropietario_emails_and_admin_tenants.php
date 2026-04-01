<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Copiar emails de users a copropietarios
        // Los copropietarios tienen user_id → users.email
        DB::statement('
            UPDATE copropietarios c
            INNER JOIN users u ON u.id = c.user_id
            SET c.email = u.email
            WHERE c.user_id IS NOT NULL
            AND c.email IS NULL
        ');

        // 2. Poblar tenant_administradores con admins existentes
        // Users con rol "administrador" y tenant_id asignado
        DB::statement('
            INSERT INTO tenant_administradores (user_id, tenant_id, activo, created_at, updated_at)
            SELECT id, tenant_id, activo, NOW(), NOW()
            FROM users
            WHERE rol = "administrador" AND tenant_id IS NOT NULL
            ON DUPLICATE KEY UPDATE activo = VALUES(activo)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Limpiar emails copiados (los que vinieron de users)
        DB::statement('
            UPDATE copropietarios c
            INNER JOIN users u ON u.id = c.user_id
            SET c.email = NULL
            WHERE c.user_id IS NOT NULL
        ');

        // Limpiar tenant_administradores (solo los creados por esta migración)
        DB::statement('
            DELETE ta FROM tenant_administradores ta
            INNER JOIN users u ON u.id = ta.user_id
            WHERE u.rol = "administrador"
        ');
    }
};
