<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Solo se crean los usuarios base necesarios para operar el sistema.
     * El resto de la información (copropietarios, unidades, reuniones, etc.)
     * debe cargarse mediante importación de archivos XLS/CSV desde el panel de administración.
     */
    public function run(): void
    {
        // 1. Tenant de prueba
        $tenant = Tenant::create([
            'nombre'  => 'Conjunto Test',
            'nit'     => '900123456-1',
            'activo'  => true,
        ]);

        // 2. Super administrador del SaaS (sin tenant)
        User::create([
            'name'               => 'Super Admin',
            'email'              => 'super@asambli.co',
            'password'           => Hash::make('password'),
            'rol'                => 'super_admin',
            'tenant_id'          => null,
            'email_verified_at'  => now(),
        ]);

        // 3. Administrador del conjunto de prueba
        $admin = User::create([
            'name'               => 'Admin Conjunto Test',
            'email'              => 'admin@conjuntotest.co',
            'password'           => Hash::make('password'),
            'rol'                => 'administrador',
            'tenant_id'          => $tenant->id,
            'email_verified_at'  => now(),
        ]);

        \App\Models\TenantAdministrador::create([
            'tenant_id' => $tenant->id,
            'user_id'   => $admin->id,
            'activo'    => true,
        ]);
    }
}
