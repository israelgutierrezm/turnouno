<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\Stripe\ClienteStripe;

/**
 * Adaptador de Stripe (tarjeta, OXXO). Con `secret_key` configurada crea un
 * PaymentIntent real vía la API REST y devuelve su id como referencia (queda
 * `pendiente`; se confirma por webhook). Sin llave, cae al intento simulado para
 * demo/pruebas.
 */
class PasarelaStripe extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'stripe';
    }

    protected function crearIntento(Pago $pago): ResultadoPago
    {
        $secretKey = $this->config->llave('secret_key');
        if ($secretKey === null) {
            return parent::crearIntento($pago);
        }

        $intent = (new ClienteStripe($secretKey))->crearPaymentIntent(
            $pago->monto_minor,
            $pago->moneda,
            $pago->metodo?->value,
        );

        return ResultadoPago::pendiente($intent['id'], [
            'tipo' => 'client_secret',
            'client_secret' => $intent['client_secret'],
        ]);
    }
}
