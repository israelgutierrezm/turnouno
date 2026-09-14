<?php

declare(strict_types=1);

namespace App\Modules\Organizaciones\Http\Controllers;

use App\Modules\Organizaciones\Application\CrearOrganizacion;
use App\Modules\Organizaciones\Http\Requests\CrearOrganizacionRequest;
use App\Modules\Organizaciones\Models\Organizacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrganizacionController
{
    public function index(): JsonResponse
    {
        Gate::authorize('organizaciones.ver');

        $organizaciones = Organizacion::query()->orderBy('id')->get();

        return response()->json([
            'data' => $organizaciones->map(static fn (Organizacion $organizacion): array => [
                'id' => $organizacion->ulid,
                'nombre' => $organizacion->nombre,
                'slug' => $organizacion->slug,
            ])->all(),
        ]);
    }

    public function store(CrearOrganizacionRequest $request, CrearOrganizacion $crearOrganizacion): JsonResponse
    {
        Gate::authorize('organizaciones.gestionar');

        $organizacion = $crearOrganizacion->ejecutar((string) $request->validated('nombre'));

        return response()->json([
            'data' => [
                'id' => $organizacion->ulid,
                'nombre' => $organizacion->nombre,
                'slug' => $organizacion->slug,
            ],
        ], 201);
    }
}
