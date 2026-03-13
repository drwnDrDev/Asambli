<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Reunion;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuickAccessController extends Controller
{
    // GET /acceso-rapido
    public function showPin()
    {
        return Inertia::render('Auth/AccesoRapido');
    }

    // POST /acceso-rapido
    public function storePin(Request $request)
    {
        $data = $request->validate([
            'tipo_documento'   => 'required|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento' => 'required|string|max:30',
            'pin'              => 'required|string|size:6',
        ]);

        // Buscar copropietario por documento (sin global scope de tenant, ya que no hay sesión)
        $copropietario = Copropietario::withoutGlobalScopes()
            ->where('tipo_documento', $data['tipo_documento'])
            ->where('numero_documento', $data['numero_documento'])
            ->where('activo', true)
            ->first();

        $user = $copropietario?->user;

        // Validar PIN
        if (
            !$user ||
            $user->quick_pin !== $data['pin'] ||
            !$user->pin_expires_at ||
            $user->pin_expires_at->isPast()
        ) {
            return back()->withErrors(['pin' => 'PIN incorrecto, expirado o documento no encontrado.']);
        }

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('sala.index');
    }

    // GET /sala/entrada/{token}
    public function showQr(string $token)
    {
        $reunion = Reunion::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->whereNotNull('qr_expires_at')
            ->first();

        if (!$reunion || $reunion->qr_expires_at->isPast()) {
            abort(410, 'Este código QR ha expirado o no es válido.');
        }

        return Inertia::render('Copropietario/EntradaQR', [
            'reunion' => $reunion->only('id', 'titulo'),
            'token'   => $token,
        ]);
    }

    // POST /sala/entrada/{token}
    public function storeQr(Request $request, string $token)
    {
        $data = $request->validate([
            'numero_documento' => 'required|string|max:30',
        ]);

        // Validar QR
        $reunion = Reunion::withoutGlobalScopes()
            ->where('qr_token', $token)
            ->whereNotNull('qr_expires_at')
            ->first();

        if (!$reunion || $reunion->qr_expires_at->isPast()) {
            abort(410, 'Este código QR ha expirado.');
        }

        // Buscar copropietario en el mismo tenant de la reunión
        $copropietario = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('numero_documento', $data['numero_documento'])
            ->where('activo', true)
            ->first();

        if (!$copropietario) {
            return back()->withErrors(['numero_documento' => 'Cédula no encontrada en este conjunto.']);
        }

        auth()->login($copropietario->user);
        $request->session()->regenerate();

        return redirect()->route('sala.show', $reunion->id);
    }
}
