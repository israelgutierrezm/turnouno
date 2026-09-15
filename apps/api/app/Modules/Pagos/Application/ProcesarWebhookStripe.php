<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\Stripe\VerificarFirmaStripe;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Procesa un webhook de Stripe: ubica el pago por el PaymentIntent (global),
 * fija el tenant, **verifica la firma** con el `webhook_secret` del tenant y solo
 * entonces confirma o rechaza. Idempotente (no re-cumple una orden ya pagada).
 * Devuelve true si la firma fue válida y se procesó; false si se rechaza.
 *
 * @param  array<string, mixed>  $evento
 */
class ProcesarWebhookStripe
{
    public function __construct(
        private readonly AprobarPago $aprobar,
        private readonly TenantContext $contexto,
    ) {}

    /**
     * @param  array<string, mixed>  $evento
     */
    public function ejecutar(array $evento, string $cuerpoCrudo, ?string $firma): bool
    {
        $tipo = is_string($evento['type'] ?? null) ? $evento['type'] : '';
        $intentId = $this->intentId($evento);
        if ($intentId === null) {
            return false;
        }

        $pago = Pago::query()
            ->withoutGlobalScope('tenant')
            ->where('proveedor', 'stripe')
            ->where('referencia_externa', $intentId)
            ->first();

        if ($pago === null) {
            return false;
        }

        $tenant = Tenant::query()->find($pago->tenant_id);
        if ($tenant === null) {
            return false;
        }
        $this->contexto->set($tenant);

        $secreto = ConfiguracionPasarela::query()->where('proveedor', 'stripe')->first()?->llave('webhook_secret');
        if ($secreto === null || ! VerificarFirmaStripe::valida($cuerpoCrudo, $firma, $secreto)) {
            return false;
        }

        DB::transaction(function () use ($pago, $tipo, $intentId): void {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();
            if ($bloqueado->estado !== EstadoPago::Pendiente) {
                return; // idempotente
            }

            if ($tipo === 'payment_intent.succeeded') {
                $orden = Orden::query()->whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
                if ($orden->estado !== EstadoOrden::Pagada) {
                    $this->aprobar->ejecutar($bloqueado, $orden, $intentId);
                }
            } elseif ($tipo === 'payment_intent.payment_failed') {
                $bloqueado->update(['estado' => EstadoPago::Rechazado->value]);
            }
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $evento
     */
    private function intentId(array $evento): ?string
    {
        $data = $evento['data'] ?? null;
        $objeto = is_array($data) ? ($data['object'] ?? null) : null;
        $id = is_array($objeto) ? ($objeto['id'] ?? null) : null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
