<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\MercadoPago\ClienteMercadoPago;
use App\Modules\Pagos\Pasarelas\MercadoPago\VerificarFirmaMercadoPago;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Procesa un webhook de Mercado Pago (URL por tenant). Verifica la firma
 * `x-signature`, consulta el pago en la API para leer su `external_reference` (el
 * ulid de nuestro pago) y su estado, y confirma o rechaza. Idempotente.
 */
class ProcesarWebhookMercadoPago
{
    public function __construct(
        private readonly AprobarPago $aprobar,
        private readonly TenantContext $contexto,
    ) {}

    public function ejecutar(Tenant $tenant, string $dataId, string $requestId, ?string $xSignature): bool
    {
        $this->contexto->set($tenant);

        if ($dataId === '') {
            return false;
        }

        $config = ConfiguracionPasarela::query()->where('proveedor', 'mercadopago')->first();
        $secreto = $config?->llave('webhook_secret');
        if ($secreto === null || ! VerificarFirmaMercadoPago::valida($dataId, $requestId, $xSignature, $secreto)) {
            return false;
        }

        $accessToken = $config->llave('access_token');
        if ($accessToken === null) {
            return false;
        }

        $pagoMp = (new ClienteMercadoPago($accessToken))->obtenerPago($dataId);
        $ulid = $pagoMp['external_reference'];
        if ($ulid === '') {
            return false;
        }

        $pago = Pago::query()->where('proveedor', 'mercadopago')->where('ulid', $ulid)->first();
        if ($pago === null) {
            return false;
        }

        DB::transaction(function () use ($pago, $pagoMp, $dataId): void {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();
            if ($bloqueado->estado !== EstadoPago::Pendiente) {
                return; // idempotente
            }

            if ($pagoMp['status'] === 'approved') {
                $orden = Orden::query()->whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
                if ($orden->estado !== EstadoOrden::Pagada) {
                    $this->aprobar->ejecutar($bloqueado, $orden, $dataId);
                }
            } elseif (in_array($pagoMp['status'], ['rejected', 'cancelled'], true)) {
                $bloqueado->update(['estado' => EstadoPago::Rechazado->value]);
            }
        });

        return true;
    }
}
