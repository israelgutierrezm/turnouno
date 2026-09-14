<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Controllers;

use App\Modules\Catalogo\Http\Requests\AgregarNivelRequest;
use App\Modules\Catalogo\Models\Actividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class NivelController
{
    public function store(AgregarNivelRequest $request, Actividad $actividad): JsonResponse
    {
        Gate::authorize('catalogo.gestionar');

        $orden = $request->validated('orden');
        $nivel = $actividad->niveles()->create([
            'nombre' => (string) $request->validated('nombre'),
            'orden' => is_numeric($orden) ? (int) $orden : 0,
        ]);

        return response()->json([
            'data' => ['id' => $nivel->ulid, 'nombre' => $nivel->nombre, 'orden' => $nivel->orden],
        ], 201);
    }
}
