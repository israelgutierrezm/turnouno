<?php

declare(strict_types=1);

namespace App\Modules\Recursos\Http\Controllers;

use App\Modules\Recursos\Http\Requests\CrearRecursoRequest;
use App\Modules\Recursos\Models\Instalacion;
use App\Modules\Recursos\Models\Recurso;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RecursoController
{
    public function store(CrearRecursoRequest $request, Instalacion $instalacion): JsonResponse
    {
        Gate::authorize('recursos.gestionar');

        // Resolvemos el recurso padre (opcional) por su ULID, dentro del tenant.
        $padreUlid = $request->validated('recurso_padre_id');
        $recursoPadreId = null;
        if (is_string($padreUlid) && $padreUlid !== '') {
            $recursoPadreId = Recurso::query()->where('ulid', $padreUlid)->firstOrFail()->id;
        }

        $tipo = $request->validated('tipo');
        $capacidad = $request->validated('capacidad');

        $recurso = $instalacion->recursos()->create([
            'recurso_padre_id' => $recursoPadreId,
            'nombre' => (string) $request->validated('nombre'),
            'tipo' => is_string($tipo) && $tipo !== '' ? $tipo : null,
            'modo' => (string) $request->validated('modo'),
            'capacidad' => is_numeric($capacidad) ? (int) $capacidad : 1,
        ]);

        return response()->json([
            'data' => [
                'id' => $recurso->ulid,
                'nombre' => $recurso->nombre,
                'modo' => $recurso->modo->value,
                'capacidad' => $recurso->capacidad,
            ],
        ], 201);
    }
}
