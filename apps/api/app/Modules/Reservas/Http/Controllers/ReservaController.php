<?php

declare(strict_types=1);

namespace App\Modules\Reservas\Http\Controllers;

use App\Modules\Agenda\Models\Sesion;
use App\Modules\Personas\Models\Persona;
use App\Modules\Reservas\Application\CancelarReserva;
use App\Modules\Reservas\Application\CrearReserva;
use App\Modules\Reservas\EstadoReserva;
use App\Modules\Reservas\Http\Requests\CrearReservaRequest;
use App\Modules\Reservas\Models\Reserva;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Reservas de una sesión: crear (motor de booking), cancelar y listar el roster.
 */
class ReservaController
{
    public function index(Sesion $sesion): JsonResponse
    {
        Gate::authorize('reservas.ver');

        $reservas = Reserva::query()
            ->where('sesion_id', $sesion->id)
            ->where('estado', EstadoReserva::Confirmada->value)
            ->with('persona')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $reservas->map(fn (Reserva $reserva): array => $this->datos($reserva))->all(),
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
        );

        return response()->json(['data' => $this->datos($reserva)], 201);
    }

    public function cancelar(Reserva $reserva, CancelarReserva $cancelar): JsonResponse
    {
        Gate::authorize('reservas.cancelar');

        $cancelar->ejecutar($reserva);

        return response()->json(['data' => $this->datos($reserva->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Reserva $reserva): array
    {
        $reserva->loadMissing(['persona', 'sesion']);

        return [
            'id' => $reserva->ulid,
            'estado' => $reserva->estado->value,
            'persona' => trim($reserva->persona->nombre.' '.($reserva->persona->apellidos ?? '')),
            'inicia_en' => $reserva->sesion->inicia_en->toIso8601String(),
            'unidades' => $reserva->unidades,
        ];
    }
}
