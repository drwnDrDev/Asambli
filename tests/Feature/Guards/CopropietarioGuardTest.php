<?php
use App\Models\AccesoReunion;
use App\Models\Reunion;
use App\Enums\ReunionEstado;
use Illuminate\Support\Facades\Route;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// Registrar una ruta de prueba que expone el resultado del guard via JSON
beforeEach(function () {
    Route::get('/_test/copropietario-guard', function () {
        return response()->json([
            'check' => auth('copropietario')->check(),
            'id'    => auth('copropietario')->id(),
        ]);
    })->middleware('web');
});

it('devuelve false si no hay session_token en sesión', function () {
    $response = $this->get('/_test/copropietario-guard');
    $response->assertStatus(200);
    $response->assertJson(['check' => false, 'id' => null]);
});

it('autentica correctamente con un token válido en reunión activa', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::EnCurso]);
    $acceso = AccesoReunion::factory()
        ->for($reunion)
        ->create(['activo' => true]);

    $token = 'token-valido-test-' . uniqid();
    \Illuminate\Support\Facades\DB::table('acceso_reunion')
        ->where('id', $acceso->id)
        ->update(['session_token' => $token]);

    $response = $this->withSession(['copropietario_session_token' => $token])
        ->get('/_test/copropietario-guard');

    $response->assertStatus(200);
    $response->assertJson(['check' => true, 'id' => $acceso->copropietario_id]);
});

it('rechaza token de reunión finalizada', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::Finalizada]);
    $acceso = AccesoReunion::factory()
        ->for($reunion)
        ->create(['activo' => true]);

    $token = 'token-finalizada-' . uniqid();
    \Illuminate\Support\Facades\DB::table('acceso_reunion')
        ->where('id', $acceso->id)
        ->update(['session_token' => $token]);

    $response = $this->withSession(['copropietario_session_token' => $token])
        ->get('/_test/copropietario-guard');

    $response->assertStatus(200);
    $response->assertJson(['check' => false]);
});

it('rechaza acceso inactivo', function () {
    $reunion = Reunion::factory()->create(['estado' => ReunionEstado::EnCurso]);
    $acceso = AccesoReunion::factory()
        ->for($reunion)
        ->create(['activo' => false]);

    $token = 'token-inactivo-' . uniqid();
    \Illuminate\Support\Facades\DB::table('acceso_reunion')
        ->where('id', $acceso->id)
        ->update(['session_token' => $token]);

    $response = $this->withSession(['copropietario_session_token' => $token])
        ->get('/_test/copropietario-guard');

    $response->assertStatus(200);
    $response->assertJson(['check' => false]);
});
