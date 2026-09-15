<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Controllers;

use App\Modules\Creditos\LibroMayor;
use App\Modules\Membresias\Application\AgregarTopUp;
use App\Modules\Membresias\Http\Requests\TopUpRequest;
use App\Modules\Membresias\Models\Derecho;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Add-on / top-up sobre un derecho: suma créditos como un asiento separado, sin
 * editar la membresía.
 */
class TopUpController
{
    public function store(TopUpRequest $request, Derecho $derecho, AgregarTopUp $agregar, LibroMayor $libro): JsonResponse
    {
        Gate::authorize('membresias.gestionar');

        $descripcion = $request->validated('descripcion');

        $agregar->ejecutar(
            $derecho,
            (int) $request->validated('unidades'),
            is_string($descripcion) && $descripcion !== '' ? $descripcion : null,
        );

        return response()->json([
            'data' => [
                'id' => $derecho->ulid,
                'saldo_unidades' => $libro->saldo($derecho),
                'disponible_unidades' => $libro->disponible($derecho),
            ],
        ], 201);
    }
}
