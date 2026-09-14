<?php

declare(strict_types=1);

namespace App\Modules\Creditos\Http\Controllers;

use App\Modules\Creditos\Application\ConfirmarRetencion;
use App\Modules\Creditos\Application\LiberarRetencion;
use App\Modules\Creditos\Application\PerderRetencion;
use App\Modules\Creditos\Application\RetenerCreditos;
use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\Http\Requests\RetenerRequest;
use App\Modules\Creditos\Models\RetencionCredito;
use App\Modules\Membresias\Models\Derecho;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Ciclo de vida de una retención (hold): crear, confirmar, liberar, perder.
 */
class RetencionController
{
    public function store(RetenerRequest $request, Derecho $derecho, RetenerCreditos $retener): JsonResponse
    {
        Gate::authorize('membresias.gestionar');

        $retencion = $retener->ejecutar($derecho, (int) $request->validated('unidades'));

        return response()->json([
            'data' => ['id' => $retencion->ulid, 'estado' => $retencion->estado->value],
        ], 201);
    }

    public function confirmar(RetencionCredito $retencion, ConfirmarRetencion $confirmar): JsonResponse
    {
        Gate::authorize('membresias.gestionar');
        $confirmar->ejecutar($retencion);

        return response()->json(['data' => ['id' => $retencion->ulid, 'estado' => EstadoRetencion::Consumida->value]]);
    }

    public function liberar(RetencionCredito $retencion, LiberarRetencion $liberar): JsonResponse
    {
        Gate::authorize('membresias.gestionar');
        $liberar->ejecutar($retencion);

        return response()->json(['data' => ['id' => $retencion->ulid, 'estado' => EstadoRetencion::Liberada->value]]);
    }

    public function perder(RetencionCredito $retencion, PerderRetencion $perder): JsonResponse
    {
        Gate::authorize('membresias.gestionar');
        $perder->ejecutar($retencion);

        return response()->json(['data' => ['id' => $retencion->ulid, 'estado' => EstadoRetencion::Perdida->value]]);
    }
}
