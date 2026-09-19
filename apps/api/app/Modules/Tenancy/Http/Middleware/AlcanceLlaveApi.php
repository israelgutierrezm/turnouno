<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Models\LlaveApiTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige que la llave de API autenticada tenga el alcance (scope) indicado (R40).
 * Debe correr después de AutenticarLlaveApi.
 */
class AlcanceLlaveApi
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $llave = $request->attributes->get('llave_api');

        abort_unless($llave instanceof LlaveApiTenant && $llave->tieneAlcance($scope), 403);

        return $next($request);
    }
}
