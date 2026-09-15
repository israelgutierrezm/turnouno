<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Application\ProcesarWebhookMercadoPago;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook público de Mercado Pago, con el tenant en la URL (así se sabe qué
 * access token usar para consultar el pago). La firma se verifica en el use case.
 */
class WebhookMercadoPagoController
{
    public function __invoke(Request $request, string $tenant, ProcesarWebhookMercadoPago $procesar): JsonResponse
    {
        $tenantModel = Tenant::query()->where('ulid', $tenant)->first();
        abort_if($tenantModel === null, 404);

        $evento = json_decode($request->getContent(), true);
        $data = is_array($evento) ? ($evento['data'] ?? null) : null;
        $dataId = is_array($data) ? (string) ($data['id'] ?? '') : '';

        $ok = $procesar->ejecutar(
            $tenantModel,
            $dataId,
            (string) $request->header('x-request-id', ''),
            $request->header('x-signature'),
        );

        abort_unless($ok, 400);

        return response()->json(['data' => ['recibido' => true]]);
    }
}
