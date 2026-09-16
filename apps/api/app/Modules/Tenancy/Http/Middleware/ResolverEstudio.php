<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Middleware;

use App\Modules\Tenancy\Database\GestorDeConexionTenant;
use App\Modules\Tenancy\Models\Estudio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el estudio (tenant) ANTES de autenticar, a partir del slug de la ruta
 * (`/app/{estudio}/...`), y activa su conexión de data plane. Falla de forma
 * segura (404) si el estudio no existe o no está operativo, sin revelar otros
 * tenants. Limpia la conexión al terminar.
 */
class ResolverEstudio
{
    public function __construct(private readonly GestorDeConexionTenant $gestor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('estudio');

        $estudio = Estudio::query()->where('slug', $slug)->first();

        if (! $estudio instanceof Estudio || ! $estudio->estado->operativo()) {
            abort(404, 'Estudio no encontrado.');
        }

        $this->gestor->conectar($estudio);
        $request->attributes->set('estudio', $estudio);

        // Aislamiento de logs: cada linea de esta request queda etiquetada con el
        // estudio (junto al X-Correlation-ID) para trazabilidad por tenant.
        Log::withContext(['estudio' => $estudio->slug]);

        try {
            return $next($request);
        } finally {
            $this->gestor->desconectar();
        }
    }
}
