<?php

namespace Database\Seeders;

use App\Models\Copropietario;
use App\Models\Tenant;
use App\Models\Unidad;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CopropietarioOnboardingSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener o crear el primer tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'nombre' => 'Conjunto Test',
                'slug' => 'conjunto-test',
                'activo' => true,
            ]);
        }

        // Copropietario 1 — No activado
        $user1 = User::firstOrCreate(
            ['email' => 'ana@test.com'],
            [
                'name' => 'Ana García',
                'password' => bcrypt('password'),
                'rol' => 'copropietario',
                'tenant_id' => $tenant->id,
                'onboarded_at' => null,
                'quick_pin' => null,
            ]
        );

        $copropietario1 = Copropietario::firstOrCreate(
            ['numero_documento' => '1001001001'],
            [
                'user_id' => $user1->id,
                'tenant_id' => $tenant->id,
                'tipo_documento' => 'CC',
                'telefono' => '3001001001',
                'es_residente' => true,
                'activo' => true,
            ]
        );

        Unidad::firstOrCreate(
            ['numero' => '101', 'tenant_id' => $tenant->id],
            [
                'copropietario_id' => $copropietario1->id,
                'tipo' => 'apartamento',
                'coeficiente' => 0.05000,
                'activo' => true,
                'tenant_id' => $tenant->id,
            ]
        );

        // Copropietario 2 — Activado con contraseña
        $user2 = User::firstOrCreate(
            ['email' => 'luis@test.com'],
            [
                'name' => 'Luis Torres',
                'password' => bcrypt('password'),
                'rol' => 'copropietario',
                'tenant_id' => $tenant->id,
                'onboarded_at' => now()->subDay(),
                'quick_pin' => null,
            ]
        );

        $copropietario2 = Copropietario::firstOrCreate(
            ['numero_documento' => '2002002002'],
            [
                'user_id' => $user2->id,
                'tenant_id' => $tenant->id,
                'tipo_documento' => 'CC',
                'telefono' => '3002002002',
                'es_residente' => true,
                'activo' => true,
            ]
        );

        Unidad::firstOrCreate(
            ['numero' => '202', 'tenant_id' => $tenant->id],
            [
                'copropietario_id' => $copropietario2->id,
                'tipo' => 'apartamento',
                'coeficiente' => 0.05000,
                'activo' => true,
                'tenant_id' => $tenant->id,
            ]
        );

        // Copropietario 3 — Activado CON PIN
        $user3 = User::firstOrCreate(
            ['email' => 'carmen@test.com'],
            [
                'name' => 'Carmen Ruiz',
                'password' => bcrypt('password'),
                'rol' => 'copropietario',
                'tenant_id' => $tenant->id,
                'onboarded_at' => now()->subDays(2),
                'quick_pin' => '123456',
                'pin_expires_at' => now()->addHours(48),
            ]
        );

        $copropietario3 = Copropietario::firstOrCreate(
            ['numero_documento' => '3003003003'],
            [
                'user_id' => $user3->id,
                'tenant_id' => $tenant->id,
                'tipo_documento' => 'CC',
                'telefono' => '3003003003',
                'es_residente' => true,
                'activo' => true,
            ]
        );

        Unidad::firstOrCreate(
            ['numero' => '303', 'tenant_id' => $tenant->id],
            [
                'copropietario_id' => $copropietario3->id,
                'tipo' => 'apartamento',
                'coeficiente' => 0.05000,
                'activo' => true,
                'tenant_id' => $tenant->id,
            ]
        );

        // Imprimir credenciales de prueba
        $this->command->info('=== CREDENCIALES DE PRUEBA (ONBOARDING) ===');
        $this->command->info('Ana García (NO activada): ana@test.com / [sin contraseña útil aún — usar magic link de onboarding]');
        $this->command->info('  Cédula: CC 1001001001');
        $this->command->info('Luis Torres (activado): luis@test.com / password');
        $this->command->info('  Cédula: CC 2002002002');
        $this->command->info('Carmen Ruiz (con PIN): carmen@test.com / password  |  PIN: 123456');
        $this->command->info('  Cédula: CC 3003003003');
    }
}
