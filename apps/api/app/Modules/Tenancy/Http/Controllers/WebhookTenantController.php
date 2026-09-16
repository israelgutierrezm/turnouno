<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Pagos\Pasarelas\Stripe\VerificarFirmaStripe;
use App\Modules\Tenancy\Application\ConfirmarPagoTenant;
use App\Modules\Tenancy\Pasarelas\RegistroDePasarelasTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook publico de pasarela por estudio: `/webhooks/tenant/{estudio}/{proveedor}`.
 * El estudio se resuelve (y su BD se conecta) por `estudio.resolver`. Confirma el
 * pago pendiente correspondiente -> fulfillment. Idempotente. Para Stripe verifica
 * la firma con la `webhook_secret` del estudio cuando esta configurada.
 */
class WebhookTenantController
{
    public function __construct(
        private readonly ConfirmarPagoTenant $confirmar,
        private readonly RegistroDePasarelasTenant $registro,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $proveedor = (string) $request->route('proveedor');

        if ($proveedor === 'stripe') {
            return $this->stripe($request);
        }

        // Otros proveedores: confirmacion por referencia explicita (sim/pruebas hasta
        // conectar su webhook firmado real).
        $referencia = (string) $request->input('referencia', '');
        if ($referencia !== '') {
            $this->confirmar->porReferencia($referencia);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    private function stripe(Request $request): JsonResponse
    {
        $secret = $this->registro->llaves('stripe')['webhook_secret'] ?? '';

        // Con llave configurada, la firma es obligatoria; sin llave (aun no activo)
        // se procesa igual para no bloquear pruebas/simulacion.
        if ($secret !== '' && ! VerificarFirmaStripe::valida($request->getContent(), $request->header('Stripe-Signature'), $secret)) {
            abort(400, 'Firma invalida.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $tipo = isset($payload['type']) ? (string) $payload['type'] : '';
        $objeto = $payload['data']['object'] ?? [];
        $referencia = is_array($objeto) && isset($objeto['id']) ? (string) $objeto['id'] : '';

        if ($tipo === 'payment_intent.succeeded' && $referencia !== '') {
            $this->confirmar->porReferencia($referencia);
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
