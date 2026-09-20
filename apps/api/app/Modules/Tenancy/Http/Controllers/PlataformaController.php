<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\EstadoFacturacion;
use App\Modules\Tenancy\Models\ConfiguracionPlataforma;
use App\Modules\Tenancy\Models\Estudio;
use App\Modules\Tenancy\ModoCobroSaas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
                'modo_cobro' => $e->modo_cobro->value,
                'precio_por_alumno_minor' => $e->precio_por_alumno_minor,
                'cuota_fija_minor' => $e->cuota_fija_minor,
                'moneda' => $e->moneda,
                'publicado' => (bool) $e->publicado,
                'pais' => $e->pais,
                'ciudad' => $e->ciudad,
                'creado_en' => $e->created_at?->toIso8601String(),
            ])->all(),
            'total' => $estudios->count(),
        ]);
    }

    /**
     * Ajusta la facturación SaaS de un estudio: modo de cobro (activos/fijo), precio por
     * alumno, cuota fija y estado de facturación. Solo el admin de la plataforma.
     */
    public function actualizarEstudio(Request $request, string $estudio): JsonResponse
    {
        $modelo = Estudio::query()->where('slug', $estudio)->firstOrFail();

        $validado = $request->validate([
            'modo_cobro' => ['required', Rule::enum(ModoCobroSaas::class)],
            'precio_por_alumno_minor' => ['required', 'integer', 'min:0'],
            'cuota_fija_minor' => ['required', 'integer', 'min:0'],
            'estado_facturacion' => ['nullable', Rule::enum(EstadoFacturacion::class)],
        ]);

        $modelo->update([
            'modo_cobro' => $validado['modo_cobro'],
            'precio_por_alumno_minor' => (int) $validado['precio_por_alumno_minor'],
            'cuota_fija_minor' => (int) $validado['cuota_fija_minor'],
            'estado_facturacion' => $validado['estado_facturacion'] ?? $modelo->estado_facturacion->value,
        ]);

        return response()->json(['data' => [
            'slug' => $modelo->slug,
            'modo_cobro' => $modelo->modo_cobro->value,
            'precio_por_alumno_minor' => $modelo->precio_por_alumno_minor,
            'cuota_fija_minor' => $modelo->cuota_fija_minor,
            'estado_facturacion' => $modelo->estado_facturacion->value,
        ]]);
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
