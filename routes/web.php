<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CopropietarioController;
use App\Http\Controllers\Admin\PadronController;
use App\Http\Controllers\Admin\PoderController as AdminPoderController;
use App\Http\Controllers\Admin\ReunionController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\Admin\VotacionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Copropietario\PoderController as CopropietarioPoderController;
use App\Http\Controllers\Copropietario\SalaReunionController;
use App\Http\Controllers\Copropietario\VotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\TenantController;
use App\Http\Controllers\SuperAdmin\ReunionController as SuperAdminReunionController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Inertia\Inertia;

// Broadcasting auth — override default (auth-only) route to accept copropietario guard too.
// Must be registered before channels.php's Broadcast::routes() to take precedence.
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth.sala']);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Default dashboard (Breeze redirects here after login/register)
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Magic link (unauthenticated)
Route::get('/acceso/{token}', [MagicLinkController::class, 'acceder'])->name('magic-link.login');

// Standard auth routes
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware(['auth', 'role:administrador,super_admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Reuniones (admin no puede crear/editar — eso lo hace super_admin)
        Route::get('reuniones', [ReunionController::class, 'index'])->name('reuniones.index');
        Route::get('reuniones/{reunion}', [ReunionController::class, 'show'])->name('reuniones.show');
        Route::delete('reuniones/{reunion}', [ReunionController::class, 'destroy'])->name('reuniones.destroy');
        Route::post('reuniones/{reunion}/convocar', [ReunionController::class, 'convocar'])->name('reuniones.convocar');
        Route::post('reuniones/{reunion}/ante-sala', [ReunionController::class, 'abrirAnteSala'])->name('reuniones.ante-sala');
        Route::post('reuniones/{reunion}/iniciar', [ReunionController::class, 'iniciar'])->name('reuniones.iniciar');
        Route::post('reuniones/{reunion}/finalizar', [ReunionController::class, 'finalizar'])->name('reuniones.finalizar');
        Route::post('reuniones/{reunion}/suspender', [ReunionController::class, 'suspender'])->name('reuniones.suspender');
        Route::post('reuniones/{reunion}/reactivar', [ReunionController::class, 'reactivar'])->name('reuniones.reactivar');
        Route::post('reuniones/{reunion}/reprogramar', [ReunionController::class, 'reprogramar'])->name('reuniones.reprogramar');
        Route::post('reuniones/{reunion}/cancelar', [ReunionController::class, 'cancelar'])->name('reuniones.cancelar');
        Route::post('reuniones/{reunion}/copropietarios/{copropietario}/asistencia', [ReunionController::class, 'confirmarAsistencia'])->name('reuniones.confirmar-asistencia');
        Route::get('reuniones/{reunion}/reporte/pdf', [ReunionController::class, 'reportePdf'])->name('reuniones.reporte-pdf');
        Route::get('reuniones/{reunion}/reporte/csv', [ReunionController::class, 'reporteCsv'])->name('reuniones.reporte-csv');
        Route::get('reuniones/{reunion}/reporte/csv-votos', [ReunionController::class, 'reporteCsvVotos'])->name('reuniones.reporte-csv-votos');
        Route::get('reuniones/{reunion}/auditoria', [ReunionController::class, 'auditoria'])->name('reuniones.auditoria');
        Route::get('reuniones/{reunion}/conducir', [ReunionController::class, 'conducir'])->name('reuniones.conducir');
        Route::post('reuniones/{reunion}/generar-qr', [ReunionController::class, 'generarQr'])->name('reuniones.generar-qr');
        Route::get('reuniones/{reunion}/proyeccion', [ReunionController::class, 'proyeccion'])->name('reuniones.proyeccion');
        Route::post('reuniones/{reunion}/aviso', [ReunionController::class, 'enviarAviso'])->name('reuniones.aviso');
        Route::post('reuniones/{reunion}/quorum-presencia', [ReunionController::class, 'actualizarQuorumPresencia'])->name('reuniones.quorum-presencia');
        Route::get('reuniones/{reunion}/lista-acceso', [\App\Http\Controllers\Admin\AccesoReunionController::class, 'show'])
            ->name('reuniones.lista-acceso');

        // Votaciones (within a reunion context)
        Route::post('reuniones/{reunion}/votaciones', [VotacionController::class, 'store'])->name('votaciones.store');
        Route::patch('votaciones/{votacion}', [VotacionController::class, 'update'])->name('votaciones.update');
        Route::delete('votaciones/{votacion}', [VotacionController::class, 'destroy'])->name('votaciones.destroy');
        Route::post('votaciones/{votacion}/abrir', [VotacionController::class, 'abrir'])->name('votaciones.abrir');
        Route::post('votaciones/{votacion}/cerrar', [VotacionController::class, 'cerrar'])->name('votaciones.cerrar');
        Route::get('votaciones/{votacion}/resultados', [VotacionController::class, 'resultados'])->name('votaciones.resultados');

        // Padrón
        Route::get('padron', [PadronController::class, 'index'])->name('padron.index');
        Route::post('padron/import', [PadronController::class, 'import'])->name('padron.import');

        // Poderes (standalone, sin reunion)
        Route::get('poderes', [AdminPoderController::class, 'index'])->name('poderes.index');
        Route::get('poderes/verificar-delegado', [AdminPoderController::class, 'verificarDelegado'])->name('poderes.verificar-delegado');
        Route::post('poderes', [AdminPoderController::class, 'store'])->name('poderes.store');
        Route::patch('poderes/{poder}/aprobar', [AdminPoderController::class, 'aprobar'])->name('poderes.aprobar');
        Route::patch('poderes/{poder}/rechazar', [AdminPoderController::class, 'rechazar'])->name('poderes.rechazar');
        Route::delete('poderes/{poder}', [AdminPoderController::class, 'destroy'])->name('poderes.destroy');

        // Configuración del conjunto (solo administrador — super_admin no tiene current_tenant)
        Route::middleware('role:administrador')->group(function () {
            Route::get('/configuracion', [TenantSettingsController::class, 'edit'])->name('configuracion');
            Route::patch('/configuracion', [TenantSettingsController::class, 'update'])->name('configuracion.update');
        });

        // Copropietarios
        Route::resource('copropietarios', CopropietarioController::class);
        Route::post('copropietarios/{copropietario}/generar-pin', [CopropietarioController::class, 'generatePin'])->name('copropietarios.generar-pin');
        Route::post('copropietarios/{copropietario}/reenviar-bienvenida', [CopropietarioController::class, 'reenviarBienvenida'])->name('copropietarios.reenviar-bienvenida');
    });

// Onboarding (unauthenticated)
use App\Http\Controllers\Auth\OnboardingController;
Route::get('/bienvenida/{token}', [OnboardingController::class, 'show'])->name('onboarding.show');
Route::post('/bienvenida/{token}', [OnboardingController::class, 'store'])->name('onboarding.store');

// Acceso rápido (PIN y QR - unauthenticated) — DEBE ir antes del grupo /sala/{reunion}
use App\Http\Controllers\Auth\QuickAccessController;
Route::get('/acceso-rapido', [QuickAccessController::class, 'showPin'])->name('quick-access.pin');
Route::post('/acceso-rapido', [QuickAccessController::class, 'storePin'])->name('quick-access.pin.store');
Route::get('/sala/entrada/{token}', [QuickAccessController::class, 'showQr'])->name('quick-access.qr');
Route::post('/sala/entrada/{token}', [QuickAccessController::class, 'storeQr'])->name('quick-access.qr.store');

// Login copropietario con documento + PIN (sin autenticación)
Route::get('/sala/login/{reunion}', [\App\Http\Controllers\Auth\CopropietarioAccessController::class, 'show'])
    ->name('sala.login');
Route::post('/sala/login/{reunion}', [\App\Http\Controllers\Auth\CopropietarioAccessController::class, 'store'])
    ->name('sala.login.store');

// Copropietario (sala) routes — User-auth only (index, historial, poderes)
Route::middleware(['auth', 'role:copropietario,administrador,super_admin'])
    ->name('sala.')
    ->group(function () {
        Route::get('/sala', [SalaReunionController::class, 'index'])->name('index');
        Route::get('/historial', [SalaReunionController::class, 'historial'])->name('historial');
        // Rutas estáticas de poderes ANTES que /sala/{reunion} para evitar colisión
        Route::get('/sala/poderes', [CopropietarioPoderController::class, 'index'])->name('poderes.index');
        Route::get('/sala/poderes/verificar-delegado', [CopropietarioPoderController::class, 'verificarDelegado'])->name('poderes.verificar-delegado');
        Route::get('/sala/poderes/crear', [CopropietarioPoderController::class, 'create'])->name('poderes.create');
        Route::post('/sala/poderes', [CopropietarioPoderController::class, 'store'])->name('poderes.store');
        Route::delete('/sala/poderes/{poder}', [CopropietarioPoderController::class, 'destroy'])->name('poderes.destroy');
    });

// Sala routes accessible by BOTH User guard AND copropietario (PIN) guard
Route::middleware(['auth.sala'])
    ->name('sala.')
    ->group(function () {
        Route::post('/votos', [VotoController::class, 'store'])->name('votos.store');
        Route::get('/sala/{reunion}/estado-actual', [SalaReunionController::class, 'estadoActual'])->name('estado-actual');
        Route::get('/sala/{reunion}', [SalaReunionController::class, 'show'])->name('show');
    });

// Super-admin routes
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::get('/dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('tenants', TenantController::class);
        Route::post('tenants/{tenant}/admins', [TenantController::class, 'storeAdmin'])->name('tenants.admins.store');
        Route::patch('tenants/{tenant}/users/{user}/toggle', [TenantController::class, 'toggleUser'])->name('tenants.users.toggle');
        Route::get('tenants/{tenant}/auditoria', [TenantController::class, 'auditoria'])->name('tenants.auditoria');
        Route::get('tenants/{tenant}/reuniones/create', [SuperAdminReunionController::class, 'create'])->name('reuniones.create');
        Route::post('tenants/{tenant}/reuniones', [SuperAdminReunionController::class, 'store'])->name('reuniones.store');
        Route::post('reuniones/{reunion}/reset-convocatoria', [SuperAdminReunionController::class, 'resetConvocatoria'])->name('reuniones.reset-convocatoria');
    });

require __DIR__.'/auth.php';
