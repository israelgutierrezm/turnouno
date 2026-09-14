<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Controllers;

use App\Modules\Catalogo\Http\Requests\CrearProgramaRequest;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Nivel;
use App\Modules\Catalogo\Models\Oferta;
use App\Modules\Catalogo\Models\Programa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProgramaController
{
    public function index(): JsonResponse
    {
        Gate::authorize('catalogo.ver');

        $programas = Programa::query()->orderBy('id')->get();

        return response()->json([
            'data' => $programas->map(static fn (Programa $programa): array => [
                'id' => $programa->ulid,
                'nombre' => $programa->nombre,
                'slug' => $programa->slug,
            ])->all(),
        ]);
    }

    public function store(CrearProgramaRequest $request): JsonResponse
    {
        Gate::authorize('catalogo.gestionar');

        $nombre = (string) $request->validated('nombre');
        $programa = Programa::create([
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.Str::lower(Str::random(4)),
        ]);

        return response()->json([
            'data' => ['id' => $programa->ulid, 'nombre' => $programa->nombre, 'slug' => $programa->slug],
        ], 201);
    }

    public function show(Programa $programa): JsonResponse
    {
        Gate::authorize('catalogo.ver');

        $programa->load(['actividades.niveles', 'actividades.ofertas']);

        return response()->json([
            'data' => [
                'id' => $programa->ulid,
                'nombre' => $programa->nombre,
                'slug' => $programa->slug,
                'actividades' => $programa->actividades->map(static fn (Actividad $actividad): array => [
                    'id' => $actividad->ulid,
                    'nombre' => $actividad->nombre,
                    'slug' => $actividad->slug,
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
                ])->all(),
            ],
        ]);
    }
}
