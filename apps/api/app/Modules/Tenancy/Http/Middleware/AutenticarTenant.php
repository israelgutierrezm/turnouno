<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Application\AutenticacionTenant;
use App\Modules\Tenancy\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica al usuario tenant-local a partir del Bearer token, resolviéndolo
 * contra la BD del tenant ya activa (debe correr después de ResolverEstudio). Un
 * token de otro estudio no existe en esta base y por tanto no autentica.
 */
class AutenticarTenant
{
    public function __construct(private readonly AutenticacionTenant $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $usuario = is_string($bearer) && $bearer !== '' ? $this->auth->resolver($bearer) : null;

        if (! $usuario instanceof Usuario || ! $usuario->activo) {
            abort(401, 'No autenticado.');
        }

        $request->setUserResolver(static fn (): Usuario => $usuario);
        // También en attributes: el resolver estándar viene tipado como el User
        // global; aquí la identidad es tenant-local (Usuario).
        $request->attributes->set('usuario_tenant', $usuario);

        return $next($request);
    }
}
