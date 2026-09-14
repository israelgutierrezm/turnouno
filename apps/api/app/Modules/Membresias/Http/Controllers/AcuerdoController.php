<?php

declare(strict_types=1);

namespace App\Modules\Membresias\Http\Controllers;

use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Membresias\Http\Requests\CrearAcuerdoRequest;
use App\Modules\Membresias\Models\ProductoComercial;
use App\Modules\Personas\Models\Persona;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Venta de un producto a una persona (crea el acuerdo + derecho + concesión).
 */
class AcuerdoController
{
    public function store(CrearAcuerdoRequest $request, Persona $persona, CrearAcuerdo $crearAcuerdo): JsonResponse
    {
        Gate::authorize('membresias.gestionar');

        $producto = ProductoComercial::query()
            ->where('ulid', (string) $request->validated('producto_id'))
            ->firstOrFail();

        $fechaInicio = $request->validated('fecha_inicio');

        $acuerdo = $crearAcuerdo->ejecutar(
            $persona,
            $producto,
            is_string($fechaInicio) && $fechaInicio !== '' ? $fechaInicio : null,
        );

        return response()->json([
            'data' => ['id' => $acuerdo->ulid, 'estado' => $acuerdo->estado->value],
        ], 201);
    }
}
