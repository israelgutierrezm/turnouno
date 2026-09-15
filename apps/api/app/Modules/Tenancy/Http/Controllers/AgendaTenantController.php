<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\EstadoSesionTenant;
use App\Modules\Tenancy\Models\OfertaTenant;
use App\Modules\Tenancy\Models\SesionTenant;
use App\Modules\Tenancy\Models\SucursalTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agenda del estudio, tenant-local. Materializa una oferta en una sucursal a una
 * hora concreta; convierte la hora local (en la zona de la sucursal) a UTC y
 * conserva el snapshot de zona. Opera sobre la BD del estudio resuelto.
 */
class AgendaTenantController
{
    private const LIMITE = 200;

    public function crearSesion(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'oferta_id' => ['required', 'string'],
            'sucursal_id' => ['required', 'string'],
            'inicia_en_local' => ['required', 'date'],
            'duracion_minutos' => ['required', 'integer', 'min:1', 'max:1440'],
            'capacidad' => ['nullable', 'integer', 'min:1'],
        ]);

        $oferta = OfertaTenant::query()->where('ulid', $validado['oferta_id'])->firstOrFail();
        $sucursal = SucursalTenant::query()->where('ulid', $validado['sucursal_id'])->firstOrFail();

        // Hora local (zona de la sucursal) → UTC; snapshot de zona en la sesión.
        $inicia = CarbonImmutable::parse((string) $validado['inicia_en_local'], (string) $sucursal->zona_horaria)->utc();
        $termina = $inicia->addMinutes((int) $validado['duracion_minutos']);

        $sesion = SesionTenant::query()->create([
            'oferta_id' => $oferta->id,
            'sucursal_id' => $sucursal->id,
            'inicia_en' => $inicia,
            'termina_en' => $termina,
            'zona_horaria' => $sucursal->zona_horaria,
            'capacidad' => $validado['capacidad'] ?? $oferta->capacidad,
            'estado' => EstadoSesionTenant::Programada->value,
        ]);

        return response()->json(['data' => $this->presentar($sesion->load('oferta'))], 201);
    }

    public function sesiones(Request $request): JsonResponse
    {
        $consulta = SesionTenant::query()->with(['oferta', 'sucursal'])->orderBy('inicia_en');

        if (is_string($request->query('sucursal_id')) && $request->query('sucursal_id') !== '') {
            $sucursal = SucursalTenant::query()->where('ulid', $request->query('sucursal_id'))->first();
            $consulta->where('sucursal_id', $sucursal instanceof SucursalTenant ? $sucursal->getKey() : 0);
        }

        if (is_string($request->query('desde')) && $request->query('desde') !== '') {
            $consulta->where('inicia_en', '>=', CarbonImmutable::parse((string) $request->query('desde'))->utc());
        }
        if (is_string($request->query('hasta')) && $request->query('hasta') !== '') {
            $consulta->where('inicia_en', '<', CarbonImmutable::parse((string) $request->query('hasta'))->addDay()->utc());
        }

        $sesiones = $consulta->limit(self::LIMITE)->get();

        return response()->json([
            'data' => $sesiones->map(fn (SesionTenant $sesion): array => $this->presentar($sesion))->all(),
        ]);
    }

    public function cancelar(Request $request): JsonResponse
    {
        $sesion = SesionTenant::query()->where('ulid', (string) $request->route('sesion'))->firstOrFail();
        $sesion->update(['estado' => EstadoSesionTenant::Cancelada->value]);

        return response()->json(['data' => $this->presentar($sesion->refresh()->load('oferta'))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(SesionTenant $sesion): array
    {
        return [
            'id' => $sesion->ulid,
            'oferta' => $sesion->oferta?->nombre,
            'inicia_en' => $sesion->inicia_en->toIso8601String(),
            'termina_en' => $sesion->termina_en->toIso8601String(),
            'zona_horaria' => $sesion->zona_horaria,
            'capacidad' => $sesion->capacidad,
            'estado' => $sesion->estado->value,
        ];
    }
}
