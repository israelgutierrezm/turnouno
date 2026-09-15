<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Controllers;

use App\Modules\Catalogo\Http\Requests\CrearActividadRequest;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Nivel;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Catalogo\Models\Programa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ActividadController
{
    /**
     * Lista las actividades del tenant (para elegir una al restringir un producto).
     */
    public function index(): JsonResponse
    {
        Gate::authorize('catalogo.ver');

        $actividades = Actividad::query()->with('programa')->orderBy('nombre')->get();

        return response()->json([
            'data' => $actividades->map(static fn (Actividad $actividad): array => [
                'id' => $actividad->ulid,
                'nombre' => $actividad->nombre,
                'programa' => $actividad->programa->nombre,
            ])->all(),
        ]);
    }

    public function store(CrearActividadRequest $request, Programa $programa): JsonResponse
    {
        Gate::authorize('catalogo.gestionar');

        $nombre = (string) $request->validated('nombre');
        $actividad = $programa->actividades()->create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.Str::lower(Str::random(4)),
        ]);

        return response()->json([
            'data' => ['id' => $actividad->ulid, 'nombre' => $actividad->nombre, 'slug' => $actividad->slug],
        ], 201);
    }

    public function show(Actividad $actividad): JsonResponse
    {
        Gate::authorize('catalogo.ver');

        $actividad->load(['niveles', 'ofertas']);

        return response()->json([
            'data' => [
                'id' => $actividad->ulid,
                'nombre' => $actividad->nombre,
                'niveles' => $actividad->niveles->map(static fn (Nivel $nivel): array => [
                    'id' => $nivel->ulid,
                    'nombre' => $nivel->nombre,
                    'orden' => $nivel->orden,
                ])->all(),
                'ofertas' => $actividad->ofertas->map(static fn (Oferta $oferta): array => [
                    'id' => $oferta->ulid,
                    'nombre' => $oferta->nombre,
                    'modalidad' => $oferta->modalidad->value,
                    'capacidad' => $oferta->capacidad,
                ])->all(),
            ],
        ]);
    }
}
