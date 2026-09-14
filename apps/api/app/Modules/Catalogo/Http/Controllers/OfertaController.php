<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Controllers;

use App\Modules\Catalogo\Http\Requests\AgregarOfertaRequest;
use App\Modules\Catalogo\Models\Actividad;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OfertaController
{
    public function store(AgregarOfertaRequest $request, Actividad $actividad): JsonResponse
    {
        Gate::authorize('catalogo.gestionar');

        $capacidad = $request->validated('capacidad');
        $oferta = $actividad->ofertas()->create([
            'nombre' => (string) $request->validated('nombre'),
            'modalidad' => (string) $request->validated('modalidad'),
            'capacidad' => is_numeric($capacidad) ? (int) $capacidad : null,
        ]);

        return response()->json([
            'data' => [
                'id' => $oferta->ulid,
                'nombre' => $oferta->nombre,
                'modalidad' => $oferta->modalidad->value,
                'capacidad' => $oferta->capacidad,
            ],
        ], 201);
    }
}
