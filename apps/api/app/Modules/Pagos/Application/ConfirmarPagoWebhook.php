<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Procesa la confirmación asíncrona de un pago (webhook de la pasarela). Es
 * **idempotente**: si el pago ya está aprobado, no vuelve a cumplir la orden, así
 * que reintentos o eventos duplicados del proveedor no conceden derechos dos veces.
 *
 * El webhook llega sin sesión ni tenant: se ubica el pago por su referencia
 * (global) y se fija el TenantContext antes de cualquier operación tenant-scoped.
 *
 * NOTA de seguridad: en producción, antes de confiar en el evento hay que
 * verificar la firma del proveedor. La `PasarelaSimulada` es solo para pruebas.
 */
class ConfirmarPagoWebhook
{
    public function __construct(
        private readonly AprobarPago $aprobar,
        private readonly TenantContext $contexto,
    ) {}

    public function ejecutar(string $proveedor, string $referencia, string $estado): void
    {
        $pago = Pago::query()
            ->withoutGlobalScope('tenant')
            ->where('proveedor', $proveedor)
            ->where('referencia_externa', $referencia)
            ->first();

        if ($pago === null) {
            return; // Referencia desconocida: se ignora.
        }

        $tenant = Tenant::query()->find($pago->tenant_id);
        if ($tenant === null) {
            return;
        }
        $this->contexto->set($tenant);

        DB::transaction(function () use ($pago, $referencia, $estado): void {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueado->estado === EstadoPago::Aprobado) {
                return; // Idempotente: la orden ya fue cumplida.
            }

            if ($estado !== 'aprobado') {
                $bloqueado->update(['estado' => EstadoPago::Rechazado->value]);

                return;
            }

            $orden = Orden::query()->whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === EstadoOrden::Pagada) {
                return;
            }

            $this->aprobar->ejecutar($bloqueado, $orden, $referencia);
        });
    }
}
