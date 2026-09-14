<?php

declare(strict_types=1);

namespace App\Modules\Agenda\Http\Controllers;

use App\Modules\Agenda\Application\CrearPlantillaHorario;
use App\Modules\Agenda\Application\GenerarSesiones;
use App\Modules\Agenda\Http\Requests\CrearPlantillaHorarioRequest;
use App\Modules\Agenda\Http\Requests\GenerarSesionesRequest;
use App\Modules\Agenda\Models\PlantillaHorario;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Models\Recurso;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Plantillas de horario de una oferta y materialización de sus sesiones.
 */
class PlantillaHorarioController
{
    public function store(CrearPlantillaHorarioRequest $request, Oferta $oferta, CrearPlantillaHorario $crear): JsonResponse
    {
        Gate::authorize('agenda.gestionar');

        $sucursal = Sucursal::query()
            ->where('ulid', (string) $request->validated('sucursal_id'))
            ->firstOrFail();

        $recurso = $this->resolverRecurso($request->validated('recurso_id'));

        $vigenteHasta = $request->validated('vigente_hasta');
        $capacidad = $request->validated('capacidad');
        $nombre = $request->validated('nombre');

        /** @var list<array{dia_semana: int, hora_inicio: string}> $reglas */
        $reglas = $request->validated('reglas');

        $plantilla = $crear->ejecutar(
            $oferta,
            $sucursal,
            [
                'duracion_minutos' => (int) $request->validated('duracion_minutos'),
                'vigente_desde' => (string) $request->validated('vigente_desde'),
                'vigente_hasta' => is_string($vigenteHasta) ? $vigenteHasta : null,
                'capacidad' => is_numeric($capacidad) ? (int) $capacidad : null,
                'nombre' => is_string($nombre) ? $nombre : null,
            ],
            $reglas,
            $recurso,
        );

        return response()->json([
            'data' => [
                'id' => $plantilla->ulid,
                'nombre' => $plantilla->nombre,
                'duracion_minutos' => $plantilla->duracion_minutos,
                'reglas' => count($reglas),
            ],
        ], 201);
    }

    public function generar(GenerarSesionesRequest $request, PlantillaHorario $plantilla, GenerarSesiones $generar): JsonResponse
    {
        Gate::authorize('agenda.gestionar');

        $creadas = $generar->ejecutar(
            $plantilla,
            (string) $request->validated('desde'),
            (string) $request->validated('hasta'),
        );

        return response()->json(['data' => ['creadas' => $creadas]], 201);
    }

    private function resolverRecurso(mixed $ulid): ?Recurso
    {
        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        return Recurso::query()->where('ulid', $ulid)->firstOrFail();
    }
}
