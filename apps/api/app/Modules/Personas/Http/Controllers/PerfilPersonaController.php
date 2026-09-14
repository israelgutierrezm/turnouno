<?php

declare(strict_types=1);

namespace App\Modules\Personas\Http\Controllers;

use App\Modules\Personas\Application\AsignarPerfil;
use App\Modules\Personas\Http\Requests\AsignarPerfilRequest;
use App\Modules\Personas\Models\Persona;
use App\Modules\Personas\TipoPerfil;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PerfilPersonaController
{
    public function store(AsignarPerfilRequest $request, Persona $persona, AsignarPerfil $asignarPerfil): JsonResponse
    {
        Gate::authorize('miembros.editar');

        $tipo = TipoPerfil::from((string) $request->validated('tipo'));
        $perfil = $asignarPerfil->ejecutar($persona, $tipo);

        return response()->json([
            'data' => ['id' => $perfil->ulid, 'tipo' => $tipo->value],
        ], 201);
    }
}
