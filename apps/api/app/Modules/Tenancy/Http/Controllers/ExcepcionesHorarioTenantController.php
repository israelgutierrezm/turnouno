<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Models\ExcepcionHorarioTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Excepciones de horario del estudio (R5): fechas (feriados/cierres) en las que la
 * generacion recurrente NO crea sesiones. Idempotente por fecha. Opera SIEMPRE sobre
 * la BD del estudio resuelto.
 */
class ExcepcionesHorarioTenantController
{
    public function index(): JsonResponse
    {
        $excepciones = ExcepcionHorarioTenant::query()->orderBy('fecha')->get();

        return response()->json([
            'data' => $excepciones->map(fn (ExcepcionHorarioTenant $e): array => [
                'id' => $e->ulid,
                'fecha' => $e->fecha->toDateString(),
                'motivo' => $e->motivo,
            ])->all(),
        ]);
    }

    public function crear(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'fecha' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $excepcion = ExcepcionHorarioTenant::query()->updateOrCreate(
            ['fecha' => $validado['fecha']],
            ['motivo' => $validado['motivo'] ?? null],
        );

        return response()->json(['data' => [
            'id' => $excepcion->ulid,
            'fecha' => $excepcion->fecha->toDateString(),
            'motivo' => $excepcion->motivo,
        ]], 201);
    }

    public function eliminar(Request $request): JsonResponse
    {
        $excepcion = ExcepcionHorarioTenant::query()->where('ulid', (string) $request->route('excepcion'))->firstOrFail();
        $excepcion->delete();

        return response()->json(status: 204);
    }
}
