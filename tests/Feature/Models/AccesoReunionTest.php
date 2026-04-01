<?php
use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('genera un PIN de 6 dígitos', function () {
    $pin = AccesoReunion::generarPin();
    expect($pin)->toHaveLength(6)->toMatch('/^\d{6}$/');
});

it('verifica el PIN correctamente', function () {
    $pin = '482917';
    $acceso = AccesoReunion::factory()->create([
        'pin_hash' => password_hash($pin, PASSWORD_BCRYPT),
    ]);

    expect($acceso->verificarPin($pin))->toBeTrue();
    expect($acceso->verificarPin('000000'))->toBeFalse();
});

it('rota el session_token e invalida el anterior', function () {
    $acceso = AccesoReunion::factory()->create(['session_token' => 'token-viejo']);

    $nuevoToken = $acceso->rotarToken();

    // session_token está en $hidden — verificar directamente en BD
    $tokenEnBD = DB::table('acceso_reunion')->where('id', $acceso->id)->value('session_token');
    expect($tokenEnBD)->toBe($nuevoToken);
    expect($nuevoToken)->not->toBe('token-viejo');
    expect(strlen($nuevoToken))->toBe(64);

    $acceso->refresh();
    expect($acceso->last_activity_at)->not->toBeNull();
});

it('tiene relación con copropietario y reunion', function () {
    $acceso = AccesoReunion::factory()->create();
    expect($acceso->copropietario)->toBeInstanceOf(Copropietario::class);
    expect($acceso->reunion)->toBeInstanceOf(Reunion::class);
});
