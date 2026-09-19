<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\ConfiguracionPlataforma;
use App\Modules\Tenancy\Models\Estudio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administración de plataforma (PlatformAdmin): el operador de TurnoUno ve todos los
 * estudios y gestiona la configuración global (p. ej. la cuenta FacturAPI usada para
 * timbrar por todos). Opera sobre el control plane (BD compartida); autenticado por
 * token de plataforma. Nunca devuelve secretos.
 */
class PlataformaController
{
    public function estudios(): JsonResponse
    {
        $estudios = Estudio::query()->orderBy('slug')->get();

        return response()->json([
            'data' => $estudios->map(static fn (Estudio $e): array => [
                'slug' => $e->slug,
                'nombre' => $e->nombre,
                'estado' => $e->estado->value,
                'estado_facturacion' => $e->estado_facturacion->value,
                'publicado' => (bool) $e->publicado,
                'pais' => $e->pais,
                'ciudad' => $e->ciudad,
                'creado_en' => $e->created_at?->toIso8601String(),
            ])->all(),
            'total' => $estudios->count(),
        ]);
    }

    public function configuracion(): JsonResponse
    {
        return response()->json(['data' => [
            // Nunca se devuelve la llave; solo si está configurada.
            'facturapi_configurada' => ConfiguracionPlataforma::llaveFacturapi() !== null,
        ]]);
    }

    public function guardarConfiguracion(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'facturapi_llave' => ['nullable', 'string', 'max:255'],
        ]);

        ConfiguracionPlataforma::establecer('facturapi_llave', $validado['facturapi_llave'] ?? null);

        return response()->json(['data' => [
            'facturapi_configurada' => ConfiguracionPlataforma::llaveFacturapi() !== null,
        ]]);
    }
}
