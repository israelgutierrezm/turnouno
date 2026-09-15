<?php

declare(strict_types=1);

namespace App\Modules\Portal\Http\Controllers;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Http\SesionPresenter;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Portal\Http\Requests\ReservarRequest;
use App\Modules\Portal\Support\MiembroActual;
use App\Modules\Reservas\Application\CrearReserva;
use App\Modules\Reservas\Http\ReservaPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

/**
 * Agenda del portal: próximas sesiones programadas del tenant y reserva del
 * miembro (siempre sobre su propia persona).
 */
class AgendaController
{
    public function __construct(private readonly MiembroActual $miembro) {}

    public function index(): JsonResponse
    {
        $sesiones = Sesion::query()
            ->where('estado', EstadoSesion::Programada->value)
            ->where('inicia_en', '>=', CarbonImmutable::now())
            ->with(['oferta', 'asignaciones.persona'])
            ->orderBy('inicia_en')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $sesiones->map(static fn (Sesion $sesion): array => SesionPresenter::datos($sesion))->all(),
        ]);
    }

    public function reservar(ReservarRequest $request, CrearReserva $crear): JsonResponse
    {
        $persona = $this->miembro->persona();

        $sesion = Sesion::query()
            ->where('ulid', (string) $request->validated('sesion_id'))
            ->firstOrFail();

        $reserva = $crear->ejecutar($sesion, $persona, null, (bool) $request->validated('esperar'));

        return response()->json(['data' => ReservaPresenter::datos($reserva)], 201);
    }
}
