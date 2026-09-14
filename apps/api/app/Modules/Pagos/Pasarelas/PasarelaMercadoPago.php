<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

/**
 * Adaptador de Mercado Pago (tarjeta, ticket en tienda, SPEI). La preferencia/pago
 * real se implementa en {@see PasarelaEnLinea::crearIntento()} con el SDK de
 * Mercado Pago y el access token del tenant.
 */
class PasarelaMercadoPago extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'mercadopago';
    }
}
