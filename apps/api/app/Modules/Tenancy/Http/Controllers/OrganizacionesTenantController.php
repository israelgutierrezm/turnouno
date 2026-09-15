<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\OrganizacionTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Organizaciones y sucursales del estudio, tenant-local (BD del tenant). Base para
 * la agenda (las sesiones ocurren en una sucursal con su zona horaria). Opera
 * SIEMPRE sobre la BD del estudio resuelto; gateado por permisos tenant-local.
 */
class OrganizacionesTenantController
{
    public function organizaciones(): JsonResponse
    {
        $organizaciones = OrganizacionTenant::query()->with('sucursales')->orderBy('id')->get();

        return response()->json([
            'data' => $organizaciones->map(fn (OrganizacionTenant $organizacion): array => [
                'id' => $organizacion->ulid,
                'nombre' => $organizacion->nombre,
                'sucursales' => $organizacion->sucursales->map(fn (SucursalTenant $sucursal): array => $this->presentarSucursal($sucursal))->all(),
            ])->all(),
        ]);
    }

    public function crearOrganizacion(Request $request): JsonResponse
    {
        $validado = $request->validate(['nombre' => ['required', 'string', 'max:255']]);

        $organizacion = OrganizacionTenant::query()->create(['nombre' => $validado['nombre']]);

        return response()->json(['data' => ['id' => $organizacion->ulid, 'nombre' => $organizacion->nombre]], 201);
    }

    public function crearSucursal(Request $request): JsonResponse
    {
        $organizacion = OrganizacionTenant::query()->where('ulid', (string) $request->route('organizacion'))->firstOrFail();

        $validado = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'zona_horaria' => ['nullable', 'timezone'],
        ]);

        $sucursal = $organizacion->sucursales()->create([
            'nombre' => $validado['nombre'],
            'zona_horaria' => $validado['zona_horaria'] ?? 'America/Mexico_City',
        ]);

        return response()->json(['data' => $this->presentarSucursal($sucursal)], 201);
    }

    public function sucursales(): JsonResponse
    {
        $sucursales = SucursalTenant::query()->orderBy('nombre')->get();

        return response()->json([
            'data' => $sucursales->map(fn (SucursalTenant $sucursal): array => $this->presentarSucursal($sucursal))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarSucursal(SucursalTenant $sucursal): array
    {
        return [
            'id' => $sucursal->ulid,
            'nombre' => $sucursal->nombre,
            'zona_horaria' => $sucursal->zona_horaria,
        ];
    }
}
