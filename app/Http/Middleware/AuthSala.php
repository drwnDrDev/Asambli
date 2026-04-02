<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware para rutas de sala que acepta tanto el guard de User
 * (admin/super_admin) como el guard copropietario (PIN-based auth).
 */
class AuthSala
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Guard estándar (admin, super_admin, o copropietario con User)
        if (auth()->check()) {
            return $next($request);
        }

        // Guard copropietario (PIN-based)
        if (auth('copropietario')->check()) {
            // Override the request user resolver so $request->user() returns
            // the Copropietario model — required for Broadcast::auth() to work.
            $copropietario = auth('copropietario')->user()->loadMissing(['unidades', 'user']);
            $request->setUserResolver(fn ($guard = null) => $copropietario);
            return $next($request);
        }

        // Redirigir al login correcto según si hay reunion en la ruta
        $reunion = $request->route('reunion');
        $reunionId = is_object($reunion) ? $reunion->id : $reunion;

        if ($reunionId) {
            return redirect("/sala/login/{$reunionId}");
        }

        return redirect('/login');
    }
}
