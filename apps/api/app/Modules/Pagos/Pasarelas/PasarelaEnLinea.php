<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Application\ConfirmarPagoWebhook;
use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Support\Str;

/**
 * Base de las pasarelas en línea (Stripe, OpenPay, Mercado Pago). Se construye con
 * la configuración del tenant (llaves + modo). Los pagos en línea son
 * **asíncronos**: `cobrar()` crea un intento y devuelve `pendiente` con una
 * referencia; el resultado real llega por webhook y lo confirma
 * {@see ConfirmarPagoWebhook}.
 *
 * INTEGRACIÓN REAL (por proveedor): en `crearIntento()` va la llamada al SDK/API
 * usando `$this->config->llave(...)` para crear el cargo/intent/voucher (tarjeta,
 * OXXO, SPEI) y devolver su referencia. Requiere llaves de sandbox reales para
 * probarse en vivo; mientras tanto se genera una referencia de intento.
 */
abstract class PasarelaEnLinea implements PasarelaDePago
{
    public function __construct(protected readonly ConfiguracionPasarela $config) {}

    public function cobrar(Pago $pago): ResultadoPago
    {
        return $this->crearIntento($pago);
    }

    protected function crearIntento(Pago $pago): ResultadoPago
    {
        // Placeholder del intento asíncrono hasta enchufar el SDK real del proveedor.
        return ResultadoPago::pendiente($this->nombre().'_'.Str::lower(Str::random(24)));
    }
}
