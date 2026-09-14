<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Http\Controllers;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Personas\Models\Persona;
use App\Modules\Reservas\Application\CancelarReserva;
use App\Modules\Reservas\Application\CrearReserva;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Http\Requests\CrearReservaRequest;
use App\Modules\Reservas\Http\ReservaPresenter;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Reservas de una sesión: crear (motor de booking), cancelar y listar el roster
 * (confirmadas + lista de espera).
 */
class ReservaController
{
    public function index(Sesion $sesion): JsonResponse
    {
        Gate::authorize('reservas.ver');

        $reservas = Reserva::query()
            ->where('sesion_id', $sesion->id)
            ->whereIn('estado', [EstadoReserva::Confirmada->value, EstadoReserva::EnEspera->value])
            ->with(['persona', 'asistencia'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $reservas->map(fn (Reserva $reserva): array => ReservaPresenter::datos($reserva))->all(),
        ]);
    }

    public function store(CrearReservaRequest $request, Sesion $sesion, CrearReserva $crear): JsonResponse
    {
        Gate::authorize('reservas.crear');

        $persona = Persona::query()
            ->where('ulid', (string) $request->validated('persona_id'))
            ->firstOrFail();

        $idempotencyKey = $request->validated('idempotency_key');

        $reserva = $crear->ejecutar(
            $sesion,
            $persona,
            is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
            (bool) $request->validated('esperar'),
        );

        return response()->json(['data' => ReservaPresenter::datos($reserva)], 201);
    }

    public function cancelar(Reserva $reserva, CancelarReserva $cancelar): JsonResponse
    {
        Gate::authorize('reservas.cancelar');

        $cancelar->ejecutar($reserva);

        return response()->json(['data' => ReservaPresenter::datos($reserva->refresh())]);
    }
}
