<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\Application\CobrarOrden;
use App\Modules\Pagos\Http\Requests\CobrarOrdenRequest;
use App\Modules\Pagos\Pasarelas\RegistroDePasarelas;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Cobro de una orden a través de una pasarela. El pago aprobado concede los
 * derechos (fulfillment) dentro de CobrarOrden.
 */
class PagoController
{
    public function store(CobrarOrdenRequest $request, Orden $orden, CobrarOrden $cobrar, RegistroDePasarelas $registro): JsonResponse
    {
        Gate::authorize('pagos.crear');

        $pasarela = $registro->para((string) $request->validated('proveedor'));
        $idempotencyKey = $request->validated('idempotency_key');

        $pago = $cobrar->ejecutar(
            $orden,
            $pasarela,
            is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
        );

        return response()->json([
            'data' => [
                'id' => $pago->ulid,
                'estado' => $pago->estado->value,
                'proveedor' => $pago->proveedor,
                'referencia' => $pago->referencia_externa,
                'orden_estado' => $orden->refresh()->estado->value,
            ],
        ], 201);
    }
}
