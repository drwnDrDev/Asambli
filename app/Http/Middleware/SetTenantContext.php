<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->rol === 'administrador') {
            $tenantId = $request->session()->get('selected_tenant_id');

            if (!$tenantId) {
                // Primer tenant activo del admin
                $tenantId = \App\Models\TenantAdministrador::where('user_id', $user->id)
                    ->where('activo', true)
                    ->value('tenant_id');
            }

            if ($tenantId) {
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                if ($tenant && $tenant->activo) {
                    app()->instance('current_tenant', $tenant);
                }
            }
        } elseif ($user->tenant_id) {
            // Comportamiento original para usuarios con tenant_id directo
            $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
            if ($tenant && $tenant->activo) {
                app()->instance('current_tenant', $tenant);
            }
        }
        // super_admin (tenant_id null, rol super_admin) no recibe tenant context → acceso global

        return $next($request);
    }
}
