<?php

use App\Enums\ReunionEstado;
use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function salaLoginInertiaVersion(): string
{
    $manifest = public_path('build/manifest.json');
    if (file_exists($manifest)) {
        return hash_file('xxh128', $manifest);
    }
    return '';
}

it('muestra la página de login de la reunión', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::AnteSala]);

    // Usar X-Inertia para que retorne JSON sin necesitar el Vite manifest
    $response = $this->withHeaders([
                         'X-Inertia'         => 'true',
                         'X-Inertia-Version' => salaLoginInertiaVersion(),
                     ])
                     ->get("/sala/login/{$reunion->id}");

    $response->assertStatus(200);
    $json = $response->json();
    expect($json['component'])->toBe('Sala/Login');
    expect($json['props']['reunion']['id'])->toBe($reunion->id);
    expect($json['props']['reunion']['tenant']['nombre'])->not->toBeNull();
});

it('autentica al copropietario con documento y PIN válidos', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::AnteSala]);
    $copropietario = Copropietario::factory()->create([
        'tenant_id'        => $reunion->tenant_id,
        'numero_documento' => '12345678',
        'activo'           => true,
    ]);
    $pin = '482917';
    AccesoReunion::factory()->create([
        'copropietario_id' => $copropietario->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => password_hash($pin, PASSWORD_BCRYPT),
        'activo'           => true,
    ]);

    $response = $this->post("/sala/login/{$reunion->id}", [
        'numero_documento' => '12345678',
        'pin'              => $pin,
    ]);

    $response->assertRedirect(route('sala.show', $reunion->id));
    expect(session('copropietario_session_token'))->not->toBeNull();
});

it('rechaza credenciales incorrectas', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::AnteSala]);
    $copropietario = Copropietario::factory()->create([
        'tenant_id'        => $reunion->tenant_id,
        'numero_documento' => '12345678',
        'activo'           => true,
    ]);
    AccesoReunion::factory()->create([
        'copropietario_id' => $copropietario->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => password_hash('000000', PASSWORD_BCRYPT),
        'activo'           => true,
    ]);

    $response = $this->post("/sala/login/{$reunion->id}", [
        'numero_documento' => '12345678',
        'pin'              => '999999',
    ]);

    $response->assertSessionHasErrors('pin');
    expect(session('copropietario_session_token'))->toBeNull();
});

it('invalida la sesión anterior al hacer un nuevo login', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::AnteSala]);
    $copropietario = Copropietario::factory()->create([
        'tenant_id'        => $reunion->tenant_id,
        'numero_documento' => '12345678',
        'activo'           => true,
    ]);
    $pin = '482917';
    $acceso = AccesoReunion::factory()->create([
        'copropietario_id' => $copropietario->id,
        'reunion_id'       => $reunion->id,
        'pin_hash'         => password_hash($pin, PASSWORD_BCRYPT),
        'activo'           => true,
    ]);
    DB::table('acceso_reunion')->where('id', $acceso->id)->update(['session_token' => 'token-dispositivo-a']);

    $this->post("/sala/login/{$reunion->id}", [
        'numero_documento' => '12345678',
        'pin'              => $pin,
    ]);

    $tokenEnBD = DB::table('acceso_reunion')->where('id', $acceso->id)->value('session_token');
    expect($tokenEnBD)->not->toBe('token-dispositivo-a');
    expect($tokenEnBD)->not->toBeNull();
});
