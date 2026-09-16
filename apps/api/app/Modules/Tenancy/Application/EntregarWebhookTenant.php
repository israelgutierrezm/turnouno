<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Application;

use App\Modules\Tenancy\Models\EntregaWebhookTenant;
use App\Modules\Tenancy\Models\WebhookSalienteTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Entrega (o reintenta) UN evento a UN endpoint saliente (R40). Firma el cuerpo con
 * HMAC-SHA256 usando el secreto del endpoint (cabecera `X-TurnoUno-Signature`) y
 * registra el resultado (estado/http_status/intentos/entregado_en/ultimo_error) en la
 * propia entrega. No lanza excepciones por fallos de red/HTTP: los deja como `fallido`
 * para reintento, de modo que un endpoint caido nunca rompe el relay del outbox.
 */
class EntregarWebhookTenant
{
    private const TIMEOUT = 5;

    public function entregar(EntregaWebhookTenant $entrega, WebhookSalienteTenant $endpoint): void
    {
        $cuerpo = json_encode($entrega->payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($cuerpo === false) {
            $cuerpo = '{}';
        }

        $firma = hash_hmac('sha256', $cuerpo, (string) $endpoint->secreto);
        $entrega->intentos++;

        try {
            $respuesta = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'X-TurnoUno-Event' => $entrega->evento_tipo,
                    'X-TurnoUno-Delivery' => (string) $entrega->ulid,
                    'X-TurnoUno-Signature' => 'sha256='.$firma,
                ])
                ->withBody($cuerpo, 'application/json')
                ->post($endpoint->url);

            $entrega->http_status = $respuesta->status();

            if ($respuesta->successful()) {
                $entrega->estado = 'entregado';
                $entrega->entregado_en = Carbon::now();
                $entrega->ultimo_error = null;
            } else {
                $entrega->estado = 'fallido';
                $entrega->ultimo_error = 'HTTP '.$respuesta->status();
            }
        } catch (Throwable $e) {
            $entrega->estado = 'fallido';
            $entrega->http_status = null;
            $entrega->ultimo_error = Str::limit($e->getMessage(), 250);
        }

        $entrega->save();
    }
}
