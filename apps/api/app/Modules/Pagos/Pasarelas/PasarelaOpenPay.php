<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

/**
 * Adaptador de OpenPay (tarjeta, tienda de conveniencia, SPEI). El cargo real se
 * implementa en {@see PasarelaEnLinea::crearIntento()} con el SDK de OpenPay y las
 * llaves del tenant (merchant id + private key).
 */
class PasarelaOpenPay extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'openpay';
    }
}
