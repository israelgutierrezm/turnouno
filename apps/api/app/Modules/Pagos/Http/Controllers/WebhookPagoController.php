<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Application\ConfirmarPagoWebhook;
use App\Modules\Pagos\Http\Requests\WebhookPagoRequest;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint público de webhooks de pago (sin sesión ni tenant). Idempotente: el
 * dominio ignora referencias desconocidas y no vuelve a cumplir órdenes ya
 * pagadas. En producción se debe verificar la firma del proveedor antes de esto.
 */
class WebhookPagoController
{
    public function __invoke(WebhookPagoRequest $request, string $proveedor, ConfirmarPagoWebhook $confirmar): JsonResponse
    {
        $confirmar->ejecutar(
            $proveedor,
            (string) $request->validated('referencia'),
            (string) $request->validated('estado'),
        );

        // Siempre 200 para acusar recibo al proveedor y no filtrar información.
        return response()->json(['data' => ['recibido' => true]]);
    }
}
