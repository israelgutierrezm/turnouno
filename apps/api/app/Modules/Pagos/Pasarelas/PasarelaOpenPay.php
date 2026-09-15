<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\OpenPay\ClienteOpenPay;

/**
 * Adaptador de OpenPay (tienda de conveniencia, SPEI, tarjeta). Con `merchant_id`
 * + `private_key` configuradas crea un cargo real y devuelve su id (queda
 * `pendiente`; se confirma por webhook). Sin llaves cae al intento simulado.
 */
class PasarelaOpenPay extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'openpay';
    }

    protected function crearIntento(Pago $pago): ResultadoPago
    {
        $merchantId = $this->config->llave('merchant_id');
        $privateKey = $this->config->llave('private_key');

        if ($merchantId === null || $privateKey === null) {
            return parent::crearIntento($pago);
        }

        $cargo = (new ClienteOpenPay($merchantId, $privateKey, $this->config->modo !== 'live'))
            ->crearCargo($pago->monto_minor, $pago->moneda, $pago->metodo?->value, 'Orden '.$pago->orden_id, $pago->datosCliente);

        $checkout = $cargo['payment_method'] === []
            ? []
            : array_merge(['tipo' => 'voucher'], $cargo['payment_method']);

        return ResultadoPago::pendiente($cargo['id'], $checkout);
    }
}
