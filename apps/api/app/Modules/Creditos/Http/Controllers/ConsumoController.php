<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Http\Controllers;

use App\Modules\Creditos\Application\ConsumirCreditos;
use App\Modules\Creditos\Http\Requests\ConsumirRequest;
use App\Modules\Membresias\Models\Derecho;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ConsumoController
{
    public function store(ConsumirRequest $request, Derecho $derecho, ConsumirCreditos $consumir): JsonResponse
    {
        Gate::authorize('membresias.gestionar');

        $descripcion = $request->validated('descripcion');

        $movimiento = $consumir->ejecutar(
            $derecho,
            (int) $request->validated('unidades'),
            is_string($descripcion) && $descripcion !== '' ? $descripcion : null,
        );

        return response()->json([
            'data' => ['id' => $movimiento->ulid, 'unidades' => $movimiento->unidades],
        ], 201);
    }
}
