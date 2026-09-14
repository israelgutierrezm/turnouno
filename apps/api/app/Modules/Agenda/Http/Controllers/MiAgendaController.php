<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Agenda\EstadoSesion;
use App\Modules\Agenda\Http\SesionPresenter;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Personas\Models\Persona;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Visibilidad de agenda del instructor: las próximas sesiones programadas donde
 * el usuario actual (a través de sus personas en el tenant) está asignado.
 */
class MiAgendaController
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('agenda.ver');

        $usuario = $request->user();
        abort_if($usuario === null, 401);

        $personaIds = Persona::query()
            ->where('user_id', $usuario->getAuthIdentifier())
            ->pluck('id');

        $sesiones = Sesion::query()
            ->whereHas('asignaciones', static function (Builder $consulta) use ($personaIds): void {
                $consulta->whereIn('persona_id', $personaIds);
            })
            ->where('estado', EstadoSesion::Programada->value)
            ->where('inicia_en', '>=', CarbonImmutable::now())
            ->with(['oferta', 'asignaciones.persona'])
            ->orderBy('inicia_en')
            ->get();

        return response()->json([
            'data' => $sesiones->map(static fn (Sesion $sesion): array => SesionPresenter::datos($sesion))->all(),
        ]);
    }
}
