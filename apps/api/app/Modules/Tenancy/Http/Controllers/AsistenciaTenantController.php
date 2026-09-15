<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Tenancy\Application\AsistenciaTenant;
use App\Modules\Tenancy\Models\ReservaTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Asistencia (check-in) del estudio, tenant-local: marca presente/ausente sobre una
 * reserva confirmada y liquida su retencion (presente consume, ausente pierde).
 * Opera SIEMPRE sobre la BD del estudio resuelto.
 */
class AsistenciaTenantController
{
    public function __construct(private readonly AsistenciaTenant $asistencia) {}

    public function marcar(Request $request): JsonResponse
    {
        $reserva = ReservaTenant::query()->where('ulid', (string) $request->route('reserva'))->firstOrFail();

        $validado = $request->validate([
            'estado' => ['required', Rule::enum(EstadoAsistencia::class)],
        ]);

        $asistencia = $this->asistencia->marcar($reserva, EstadoAsistencia::from($validado['estado']));

        return response()->json([
            'data' => [
                'reserva' => $reserva->ulid,
                'estado' => $asistencia->estado->value,
                'registrada_en' => $asistencia->registrada_en->toIso8601String(),
            ],
        ], 201);
    }
}
