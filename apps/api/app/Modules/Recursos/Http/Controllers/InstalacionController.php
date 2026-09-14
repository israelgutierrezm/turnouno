<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Http\Controllers;

use App\Modules\Organizaciones\Models\Sucursal;
use App\Modules\Recursos\Http\Requests\CrearInstalacionRequest;
use App\Modules\Recursos\Models\Instalacion;
use App\Modules\Recursos\Models\Recurso;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InstalacionController
{
    public function index(Sucursal $sucursal): JsonResponse
    {
        Gate::authorize('recursos.ver');

        $instalaciones = Instalacion::query()->where('sucursal_id', $sucursal->id)->orderBy('id')->get();

        return response()->json([
            'data' => $instalaciones->map(static fn (Instalacion $instalacion): array => [
                'id' => $instalacion->ulid,
                'nombre' => $instalacion->nombre,
            ])->all(),
        ]);
    }

    public function store(CrearInstalacionRequest $request, Sucursal $sucursal): JsonResponse
    {
        Gate::authorize('recursos.gestionar');

        $instalacion = Instalacion::create([
            'sucursal_id' => $sucursal->id,
            'nombre' => (string) $request->validated('nombre'),
        ]);

        return response()->json([
            'data' => ['id' => $instalacion->ulid, 'nombre' => $instalacion->nombre],
        ], 201);
    }

    public function show(Instalacion $instalacion): JsonResponse
    {
        Gate::authorize('recursos.ver');

        $instalacion->load('recursos.padre');

        return response()->json([
            'data' => [
                'id' => $instalacion->ulid,
                'nombre' => $instalacion->nombre,
                'recursos' => $instalacion->recursos->map(static fn (Recurso $recurso): array => [
                    'id' => $recurso->ulid,
                    'nombre' => $recurso->nombre,
                    'tipo' => $recurso->tipo,
                    'modo' => $recurso->modo->value,
                    'capacidad' => $recurso->capacidad,
                    'estado' => $recurso->estado,
                    'padre' => $recurso->padre instanceof Recurso
                        ? ['id' => $recurso->padre->ulid, 'nombre' => $recurso->padre->nombre]
                        : null,
                ])->all(),
            ],
        ]);
    }
}
