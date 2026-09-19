<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Models\PersonaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use App\Modules\Tenancy\TipoPersonaTenant;
use Illuminate\Http\JsonResponse;

/**
 * Reporte consolidado multi-sucursal (R18): una fila por sucursal con sus métricas
 * (miembros activos de casa, clases próximas) más los totales del estudio y el
 * grupo "sin sucursal" (miembros sin sucursal de casa). Opera sobre la BD del
 * estudio resuelto. Consultas agregadas (sin N+1).
 */
class ReporteSucursalesTenantController
{
    public function __invoke(): JsonResponse
    {
        $sucursales = SucursalTenant::query()->orderBy('nombre')->get();

        // Conteos agregados por sucursal (un query cada uno; clave = sucursal_id).
        $miembrosPorSucursal = PersonaTenant::query()
            ->where('tipo', TipoPersonaTenant::Miembro->value)
            ->where('activo', true)
            ->where('archivado', false)
            ->selectRaw('sucursal_id, count(*) as total')
            ->groupBy('sucursal_id')
            ->pluck('total', 'sucursal_id');

        $sesionesPorSucursal = SesionTenant::query()
            ->where('estado', EstadoSesionTenant::Programada->value)
            ->where('inicia_en', '>=', now())
            ->selectRaw('sucursal_id, count(*) as total')
            ->groupBy('sucursal_id')
            ->pluck('total', 'sucursal_id');

        $filas = $sucursales->map(function (SucursalTenant $sucursal) use ($miembrosPorSucursal, $sesionesPorSucursal): array {
            $id = (int) $sucursal->getKey();

            return [
                'id' => $sucursal->ulid,
                'nombre' => $sucursal->nombre,
                'region' => $sucursal->region,
                'moneda' => $sucursal->moneda !== null ? mb_strtoupper((string) $sucursal->moneda) : null,
                'impuesto_tasa_bps' => (int) $sucursal->impuesto_tasa_bps,
                'miembros_activos' => (int) ($miembrosPorSucursal[$id] ?? 0),
                'sesiones_proximas' => (int) ($sesionesPorSucursal[$id] ?? 0),
            ];
        });

        return response()->json(['data' => [
            'sucursales' => $filas->all(),
            'sin_sucursal' => [
                'miembros_activos' => (int) ($miembrosPorSucursal[''] ?? $miembrosPorSucursal[0] ?? 0),
            ],
            'totales' => [
                'sucursales' => $sucursales->count(),
                'miembros_activos' => (int) $miembrosPorSucursal->sum(),
                'sesiones_proximas' => (int) $sesionesPorSucursal->sum(),
            ],
        ]]);
    }
}
