<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MagicLink;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function __construct(private MagicLinkService $service) {}

    // GET /bienvenida/{token}
    public function show(string $token)
    {
        $link = MagicLink::with('user.copropietario.unidades')
            ->where('token', $token)
            ->where('type', 'onboarding')
            ->first();

        if (!$link || !$link->isValid()) {
            abort(410, 'Este enlace ha expirado o ya fue utilizado.');
        }

        $user = $link->user;
        $copropietario = $user->copropietario;

        return Inertia::render('Onboarding/Index', [
            'token' => $token,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'tipo_documento' => $copropietario?->tipo_documento,
                'numero_documento' => $copropietario?->numero_documento,
                'telefono' => $copropietario?->telefono,
            ],
            'unidades' => $copropietario?->unidades ?? [],
        ]);
    }

    // POST /bienvenida/{token}
    public function store(Request $request, string $token)
    {
        $link = MagicLink::with('user.copropietario.unidades')
            ->where('token', $token)
            ->where('type', 'onboarding')
            ->first();

        if (!$link || !$link->isValid()) {
            abort(410, 'Este enlace ha expirado o ya fue utilizado.');
        }

        $validated = $request->validate([
            'nombre'           => 'required|string|max:255',
            'tipo_documento'   => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento' => 'nullable|string|max:30',
            'telefono'         => 'nullable|string|max:20',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = $link->user;

        DB::transaction(function () use ($link, $user, $validated) {
            $user->update([
                'name'         => $validated['nombre'],
                'password'     => $validated['password'],
                'onboarded_at' => now(),
            ]);

            if ($user->copropietario) {
                $user->copropietario->update([
                    'tipo_documento'   => $validated['tipo_documento'] ?? null,
                    'numero_documento' => $validated['numero_documento'] ?? null,
                    'telefono'         => $validated['telefono'] ?? null,
                ]);
            }

            $link->update(['used_at' => now()]);
        });

        auth()->login($user);

        return redirect()->route('sala.index');
    }
}
