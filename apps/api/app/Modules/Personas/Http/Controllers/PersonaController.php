<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Controllers;

use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Lectura de personas del tenant activo. El global scope de BelongsToTenant
 * mantiene aislado el listado y el route-model binding (ADR-0007).
 */
class PersonaController
{
    public function index(): JsonResponse
    {
        Gate::authorize('miembros.ver');

        $personas = Persona::query()->orderBy('id')->get();

        return response()->json([
            'data' => $personas->map(fn (Persona $persona): array => $this->presentar($persona))->all(),
        ]);
    }

    public function show(Persona $persona): JsonResponse
    {
        Gate::authorize('miembros.ver');

        return response()->json(['data' => $this->presentar($persona)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Persona $persona): array
    {
        return [
            'id' => $persona->ulid,
            'nombre' => $persona->nombre,
            'apellidos' => $persona->apellidos,
            'email' => $persona->email,
        ];
    }
}
