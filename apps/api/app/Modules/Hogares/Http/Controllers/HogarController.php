<?php

declare(strict_types=1);

namespace App\Modules\Hogares\Http\Controllers;

use App\Modules\Hogares\Application\CrearHogar;
use App\Modules\Hogares\Http\Requests\CrearHogarRequest;
use App\Modules\Hogares\Models\Hogar;
use App\Modules\Personas\Models\Perfil;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class HogarController
{
    public function index(): JsonResponse
    {
        Gate::authorize('miembros.ver');

        $hogares = Hogar::query()->orderBy('id')->get();

        return response()->json([
            'data' => $hogares->map(static fn (Hogar $hogar): array => [
                'id' => $hogar->ulid,
                'nombre' => $hogar->nombre,
            ])->all(),
        ]);
    }

    public function store(CrearHogarRequest $request, CrearHogar $crearHogar): JsonResponse
    {
        Gate::authorize('miembros.crear');

        $hogar = $crearHogar->ejecutar((string) $request->validated('nombre'));

        return response()->json([
            'data' => ['id' => $hogar->ulid, 'nombre' => $hogar->nombre],
        ], 201);
    }

    // Vista de familia: el hogar con sus personas, perfiles y dependientes.
    public function show(Hogar $hogar): JsonResponse
    {
        Gate::authorize('miembros.ver');

        $hogar->load(['personas.perfiles', 'personas.dependientes']);

        return response()->json([
            'data' => [
                'id' => $hogar->ulid,
                'nombre' => $hogar->nombre,
                'personas' => $hogar->personas->map(static fn (Persona $persona): array => [
                    'id' => $persona->ulid,
                    'nombre' => $persona->nombre,
                    'apellidos' => $persona->apellidos,
                    'perfiles' => $persona->perfiles
                        ->map(static fn (Perfil $perfil): string => $perfil->tipo->value)
                        ->all(),
                    'dependientes' => $persona->dependientes
                        ->map(static fn (Persona $dependiente): array => ['id' => $dependiente->ulid, 'nombre' => $dependiente->nombre])
                        ->all(),
                ])->all(),
            ],
        ]);
    }
}
