<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\Application\CobrarOrden;
use App\Modules\Pagos\Application\ReembolsarPago;
use App\Modules\Pagos\Http\Requests\CobrarOrdenRequest;
use App\Modules\Pagos\MetodoPago;
use App\Modules\Pagos\Models\Pago;
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
        $metodoValor = $request->validated('metodo');
        $metodo = is_string($metodoValor) && $metodoValor !== '' ? MetodoPago::from($metodoValor) : null;

        $datosCliente = array_filter([
            'card_token' => $request->validated('card_token'),
            'device_session_id' => $request->validated('device_session_id'),
        ], static fn ($valor): bool => is_string($valor) && $valor !== '');

        $pago = $cobrar->ejecutar(
            $orden,
            $pasarela,
            is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
            $metodo,
            $datosCliente,
        );

        return response()->json([
            'data' => [
                'id' => $pago->ulid,
                'estado' => $pago->estado->value,
                'proveedor' => $pago->proveedor,
                'referencia' => $pago->referencia_externa,
                'orden_estado' => $orden->refresh()->estado->value,
                'checkout' => $pago->checkout,
            ],
        ], 201);
    }

    public function reembolsar(Pago $pago, ReembolsarPago $reembolsar): JsonResponse
    {
        Gate::authorize('pagos.reembolsar');

        $reembolsar->ejecutar($pago);
        $pago->refresh()->loadMissing('orden');

        return response()->json([
            'data' => [
                'id' => $pago->ulid,
                'estado' => $pago->estado->value,
                'orden_estado' => $pago->orden->estado->value,
            ],
        ]);
    }
}
