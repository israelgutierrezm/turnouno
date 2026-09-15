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
     * Crea un cargo y devuelve su id y estado.
     *
     * @return array{id: string, status: string}
     */
    public function crearCargo(int $montoMinor, string $moneda, ?string $metodo, string $descripcion): array
    {
        $method = match ($metodo) {
            'spei' => 'bank_account',
            'oxxo', 'ventanilla' => 'store',
            'tarjeta' => 'card',
            default => 'store',
        };

        $respuesta = Http::withBasicAuth($this->privateKey, '')
            ->acceptJson()
            ->post($this->base().'/charges', [
                'method' => $method,
                'amount' => round($montoMinor / 100, 2),
                'currency' => strtoupper($moneda),
                'description' => $descripcion,
            ])
            ->throw();

        /** @var array{id?: string, status?: string} $json */
        $json = $respuesta->json();

        return [
            'id' => (string) ($json['id'] ?? ''),
            'status' => (string) ($json['status'] ?? ''),
        ];
    }

    private function base(): string
    {
        $host = $this->sandbox ? 'https://sandbox-api.openpay.mx' : 'https://api.openpay.mx';

        return "{$host}/v1/{$this->merchantId}";
    }
}
