<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Application\GestionarLlavesApiTenant;
use App\Modules\Tenancy\Models\LlaveApiTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica una integración de terceros por su llave de API (R40), resolviéndola
 * contra la BD del tenant ya activa (debe correr después de ResolverEstudio). La
 * llave llega en la cabecera `X-API-Key` (o como Bearer). Una llave de otro estudio
 * no existe en esta base y por tanto no autentica.
 */
class AutenticarLlaveApi
{
    public function __construct(private readonly GestionarLlavesApiTenant $llaves) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secreto = $request->header('X-API-Key');
        if (! is_string($secreto) || $secreto === '') {
            $secreto = (string) $request->bearerToken();
        }

        $llave = $this->llaves->resolver($secreto);

        if (! $llave instanceof LlaveApiTenant) {
            abort(401, 'Llave de API invalida.');
        }

        $request->attributes->set('llave_api', $llave);

        return $next($request);
    }
}
