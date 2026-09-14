<?php

declare(strict_types=1);

namespace App\Modules\Catalogo\Http\Controllers;

use App\Modules\Catalogo\Http\Requests\AgregarOfertaRequest;
use App\Modules\Catalogo\Models\Actividad;
use App\Modules\Catalogo\Models\Oferta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OfertaController
{
    /**
     * Lista las ofertas del tenant (para elegir una al armar la agenda).
     */
    public function index(): JsonResponse
    {
        Gate::authorize('catalogo.ver');

        $ofertas = Oferta::query()->with('actividad')->orderBy('nombre')->get();

        return response()->json([
            'data' => $ofertas->map(static fn (Oferta $oferta): array => [
                'id' => $oferta->ulid,
                'nombre' => $oferta->nombre,
                'actividad' => $oferta->actividad->nombre,
                'modalidad' => $oferta->modalidad->value,
                'capacidad' => $oferta->capacidad,
            ])->all(),
        ]);
    }

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
