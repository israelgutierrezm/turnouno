<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

/**
 * Adaptador de Stripe (tarjeta, OXXO, SPEI vía Payment Intents). La creación real
 * del intent se implementa en {@see PasarelaEnLinea::crearIntento()} con el SDK de
 * Stripe y las llaves del tenant.
 */
class PasarelaStripe extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'stripe';
    }
}
