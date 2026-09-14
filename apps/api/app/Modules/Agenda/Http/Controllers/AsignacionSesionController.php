<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Agenda\Application\AsignarPersonalSesion;
use App\Modules\Agenda\Http\Requests\AsignarPersonalSesionRequest;
use App\Modules\Agenda\Http\SesionPresenter;
use App\Modules\Agenda\Models\Sesion;
use App\Modules\Agenda\RolSesion;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Asignación de staff (instructor/asistente) a una sesión.
 */
class AsignacionSesionController
{
    public function store(AsignarPersonalSesionRequest $request, Sesion $sesion, AsignarPersonalSesion $asignar): JsonResponse
    {
        Gate::authorize('agenda.gestionar');

        $persona = Persona::query()
            ->where('ulid', (string) $request->validated('persona_id'))
            ->firstOrFail();

        $rolInput = $request->validated('rol');
        $rol = is_string($rolInput) && $rolInput !== ''
            ? RolSesion::from($rolInput)
            : RolSesion::Instructor;

        $asignar->ejecutar($sesion, $persona, $rol);

        $sesion->load(['oferta', 'asignaciones.persona']);

        return response()->json(['data' => SesionPresenter::datos($sesion)], 201);
    }
}
