<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Http\Controllers;

use App\Modules\Pagos\Application\ProcesarWebhookStripe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook público de Stripe. Verifica la firma antes de confiar en el evento
 * (dentro del use case). Devuelve 400 si la firma no valida.
 */
class WebhookStripeController
{
    public function __invoke(Request $request, ProcesarWebhookStripe $procesar): JsonResponse
    {
        $cuerpo = $request->getContent();
        $evento = json_decode($cuerpo, true);

        abort_unless(is_array($evento), 400);

        $ok = $procesar->ejecutar($evento, $cuerpo, $request->header('Stripe-Signature'));

        abort_unless($ok, 400);

        return response()->json(['data' => ['recibido' => true]]);
    }
}
