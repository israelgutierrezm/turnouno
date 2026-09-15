<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas\OpenPay;

use Illuminate\Support\Facades\Http;

/**
 * Cliente HTTP mínimo de OpenPay (sin SDK). Autentica con la private key del
 * tenant vía HTTP Basic (usuario = private key, password vacío). Los montos van en
 * unidades mayores (pesos con decimales). Testeable con `Http::fake`.
 */
class ClienteOpenPay
{
    public function __construct(
        private readonly string $merchantId,
        private readonly string $privateKey,
        private readonly bool $sandbox,
    ) {}

    /**
     * Crea un cargo y devuelve su id, estado y payment_method (referencia/barcode
     * para pagos en tienda). Para tarjeta, `extra` lleva el token (`card_token`) y
     * el `device_session_id`.
     *
     * @param  array<string, mixed>  $extra
     * @return array{id: string, status: string, payment_method: array<string, mixed>}
     */
    public function crearCargo(int $montoMinor, string $moneda, ?string $metodo, string $descripcion, array $extra = []): array
    {
        $method = match ($metodo) {
            'spei' => 'bank_account',
            'oxxo', 'ventanilla' => 'store',
            'tarjeta' => 'card',
            default => 'store',
        };

        $payload = [
            'method' => $method,
            'amount' => round($montoMinor / 100, 2),
            'currency' => strtoupper($moneda),
            'description' => $descripcion,
        ];

        if ($method === 'card') {
            $token = $extra['card_token'] ?? null;
            if (is_string($token) && $token !== '') {
                $payload['source_id'] = $token;
            }
            $device = $extra['device_session_id'] ?? null;
            if (is_string($device) && $device !== '') {
                $payload['device_session_id'] = $device;
            }
        }

        $respuesta = Http::withBasicAuth($this->privateKey, '')
            ->acceptJson()
            ->post($this->base().'/charges', $payload)
            ->throw();

        /** @var array{id?: string, status?: string, payment_method?: array<string, mixed>} $json */
        $json = $respuesta->json();

        return [
            'id' => (string) ($json['id'] ?? ''),
            'status' => (string) ($json['status'] ?? ''),
            'payment_method' => is_array($json['payment_method'] ?? null) ? $json['payment_method'] : [],
        ];
    }

    private function base(): string
    {
        $host = $this->sandbox ? 'https://sandbox-api.openpay.mx' : 'https://api.openpay.mx';

        return "{$host}/v1/{$this->merchantId}";
    }
}
