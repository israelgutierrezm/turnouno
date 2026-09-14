<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Controllers;

use App\Modules\Personas\Application\RegistrarDependiente;
use App\Modules\Personas\Http\Requests\RegistrarDependienteRequest;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Registra un dependiente bajo una persona (que actúa como tutor).
 */
class DependientePersonaController
{
    public function store(
        RegistrarDependienteRequest $request,
        Persona $persona,
        RegistrarDependiente $registrarDependiente,
    ): JsonResponse {
        Gate::authorize('miembros.editar');

        $dependiente = $registrarDependiente->ejecutar(
            $persona,
            (string) $request->validated('nombre'),
            $this->comoTexto($request->validated('apellidos')),
            $this->comoTexto($request->validated('fecha_nacimiento')),
            $this->comoTexto($request->validated('parentesco')),
        );

        return response()->json([
            'data' => ['id' => $dependiente->ulid, 'nombre' => $dependiente->nombre],
        ], 201);
    }

    private function comoTexto(mixed $valor): ?string
    {
        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
