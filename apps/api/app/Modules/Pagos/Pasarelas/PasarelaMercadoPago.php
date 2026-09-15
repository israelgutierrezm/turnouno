<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\MercadoPago\ClienteMercadoPago;
use App\Modules\Tenancy\Models\Tenant;

/**
 * Adaptador de Mercado Pago (Checkout Pro). Con `access_token` configurado crea
 * una preferencia real usando el `ulid` del pago como `external_reference` para
 * correlacionar, y una `notification_url` por tenant. Devuelve el id de la
 * preferencia (queda `pendiente`; se confirma por webhook). Sin llave cae al
 * intento simulado.
 */
class PasarelaMercadoPago extends PasarelaEnLinea
{
    public function nombre(): string
    {
        return 'mercadopago';
    }

    protected function crearIntento(Pago $pago): ResultadoPago
    {
        $accessToken = $this->config->llave('access_token');
        if ($accessToken === null) {
            return parent::crearIntento($pago);
        }

        $tenantUlid = (string) (Tenant::query()->find($this->config->tenant_id)->ulid ?? '');
        $notificationUrl = url('/api/v1/webhooks/mercadopago/'.$tenantUlid);

        $preferencia = (new ClienteMercadoPago($accessToken))->crearPreferencia(
            $pago->monto_minor,
            $pago->moneda,
            $pago->ulid,
            'Orden '.$pago->orden_id,
            $notificationUrl,
        );

        return ResultadoPago::pendiente($preferencia['id'], [
            'tipo' => 'redirect',
            'url' => $preferencia['init_point'],
        ]);
    }
}
