<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas\MercadoPago;

use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP mínimo de Mercado Pago (sin SDK). Autentica con el access token del
 * tenant. Crea una preferencia de Checkout (con `external_reference` para
 * correlacionar) y consulta el pago desde el webhook. Montos en unidades mayores.
 */
class ClienteMercadoPago
{
    private const BASE = 'https://api.mercadopago.com';

    public function __construct(private readonly string $accessToken) {}

    /**
     * Crea una preferencia y devuelve su id y punto de pago.
     *
     * @return array{id: string, init_point: string}
     */
    public function crearPreferencia(int $montoMinor, string $moneda, string $externalReference, string $titulo, string $notificationUrl): array
    {
        $respuesta = Http::withToken($this->accessToken)
            ->acceptJson()
            ->post(self::BASE.'/checkout/preferences', [
                'items' => [[
                    'title' => $titulo,
                    'quantity' => 1,
                    'unit_price' => round($montoMinor / 100, 2),
                    'currency_id' => strtoupper($moneda),
                ]],
                'external_reference' => $externalReference,
                'notification_url' => $notificationUrl,
            ])
            ->throw();

        /** @var array{id?: string|int, init_point?: string} $json */
        $json = $respuesta->json();

        return [
            'id' => (string) ($json['id'] ?? ''),
            'init_point' => (string) ($json['init_point'] ?? ''),
        ];
    }

    /**
     * Consulta un pago por id.
     *
     * @return array{status: string, external_reference: string}
     */
    public function obtenerPago(string $paymentId): array
    {
        $respuesta = Http::withToken($this->accessToken)
            ->acceptJson()
            ->get(self::BASE.'/v1/payments/'.$paymentId)
            ->throw();

        /** @var array{status?: string, external_reference?: string} $json */
        $json = $respuesta->json();

        return [
            'status' => (string) ($json['status'] ?? ''),
            'external_reference' => (string) ($json['external_reference'] ?? ''),
        ];
    }
}
