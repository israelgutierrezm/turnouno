<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que el usuario tenant-local autenticado tenga el permiso indicado según su
 * rol (RBAC del data plane). Debe correr después de AutenticarTenant.
 */
class PermisoTenant
{
    public function handle(Request $request, Closure $next, string $permiso): Response
    {
        $usuario = $request->attributes->get('usuario_tenant');

        abort_unless($usuario instanceof Usuario && $usuario->puede($permiso), 403);

        return $next($request);
    }
}
