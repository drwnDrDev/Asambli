<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CopropietarioController;
use App\Http\Controllers\Admin\PadronController;
use App\Http\Controllers\Admin\ReunionController;
use App\Http\Controllers\Admin\VotacionController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Copropietario\SalaReunionController;
use App\Http\Controllers\Copropietario\VotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\TenantController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

        // Reuniones
        Route::resource('reuniones', ReunionController::class)->parameters(['reuniones' => 'reunion']);
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
        Route::get('reuniones/{reunion}/auditoria', [ReunionController::class, 'auditoria'])->name('reuniones.auditoria');
        Route::get('reuniones/{reunion}/conducir', [ReunionController::class, 'conducir'])->name('reuniones.conducir');
        Route::post('reuniones/{reunion}/generar-qr', [ReunionController::class, 'generarQr'])->name('reuniones.generar-qr');

        // Votaciones (within a reunion context)
        Route::post('reuniones/{reunion}/votaciones', [VotacionController::class, 'store'])->name('votaciones.store');
        Route::post('votaciones/{votacion}/abrir', [VotacionController::class, 'abrir'])->name('votaciones.abrir');
        Route::post('votaciones/{votacion}/cerrar', [VotacionController::class, 'cerrar'])->name('votaciones.cerrar');
        Route::get('votaciones/{votacion}/resultados', [VotacionController::class, 'resultados'])->name('votaciones.resultados');

        // Padrón
        Route::get('padron', [PadronController::class, 'index'])->name('padron.index');
        Route::post('padron/import', [PadronController::class, 'import'])->name('padron.import');

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

// Copropietario (sala) routes
Route::middleware(['auth', 'role:copropietario,administrador,super_admin'])
    ->name('sala.')
    ->group(function () {
        Route::get('/sala', [SalaReunionController::class, 'index'])->name('index');
        Route::get('/sala/{reunion}', [SalaReunionController::class, 'show'])->name('show');
        Route::get('/historial', [SalaReunionController::class, 'historial'])->name('historial');
        Route::post('/votos', [VotoController::class, 'store'])->name('votos.store');
    });

// Super-admin routes
Route::middleware(['auth', 'role:super_admin'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function () {
        Route::resource('tenants', TenantController::class);
    });

require __DIR__.'/auth.php';
