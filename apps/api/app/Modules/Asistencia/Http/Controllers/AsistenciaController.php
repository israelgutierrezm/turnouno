<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Http\Controllers;

use App\Modules\Asistencia\Application\MarcarAsistencia;
use App\Modules\Asistencia\EstadoAsistencia;
use App\Modules\Asistencia\Http\Requests\MarcarAsistenciaRequest;
use App\Modules\Reservas\Http\ReservaPresenter;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Check-in de una reserva confirmada (presente/ausente).
 */
class AsistenciaController
{
    public function store(MarcarAsistenciaRequest $request, Reserva $reserva, MarcarAsistencia $marcar): JsonResponse
    {
        Gate::authorize('asistencia.registrar');

        $estado = EstadoAsistencia::from((string) $request->validated('estado'));
        $marcar->ejecutar($reserva, $estado);

        return response()->json(['data' => ReservaPresenter::datos($reserva->refresh())]);
    }
}
