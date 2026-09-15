<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Models\ConfiguracionPasarela;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Tenancy\Context\TenantContext;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Procesa un webhook de OpenPay. OpenPay asegura el webhook con HTTP Basic
 * (usuario/contraseña configurados al registrarlo): se ubica el pago por el cargo,
 * se fija el tenant y se **verifican esas credenciales** contra la config del
 * tenant antes de confirmar. Idempotente.
 */
class ProcesarWebhookOpenPay
{
    public function __construct(
        private readonly AprobarPago $aprobar,
        private readonly TenantContext $contexto,
    ) {}

    /**
     * @param  array<string, mixed>  $evento
     */
    public function ejecutar(array $evento, ?string $usuario, ?string $password): bool
    {
        $tipo = is_string($evento['type'] ?? null) ? $evento['type'] : '';

        if ($tipo === 'verification') {
            return true; // Registro del webhook: solo acusar recibo.
        }

        $transaccion = $evento['transaction'] ?? null;
        $cargoId = is_array($transaccion) && is_string($transaccion['id'] ?? null) ? $transaccion['id'] : null;
        if ($cargoId === null) {
            return false;
        }

        $pago = Pago::query()
            ->withoutGlobalScope('tenant')
            ->where('proveedor', 'openpay')
            ->where('referencia_externa', $cargoId)
            ->first();

        if ($pago === null) {
            return false;
        }

        $tenant = Tenant::query()->find($pago->tenant_id);
        if ($tenant === null) {
            return false;
        }
        $this->contexto->set($tenant);

        $config = ConfiguracionPasarela::query()->where('proveedor', 'openpay')->first();
        $usuarioOk = $config?->llave('webhook_user');
        $passwordOk = $config?->llave('webhook_password');

        if ($usuarioOk === null || $passwordOk === null
            || ! hash_equals($usuarioOk, (string) $usuario)
            || ! hash_equals($passwordOk, (string) $password)) {
            return false;
        }

        DB::transaction(function () use ($pago, $tipo, $cargoId): void {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();
            if ($bloqueado->estado !== EstadoPago::Pendiente) {
                return; // idempotente
            }

            if ($tipo === 'charge.succeeded') {
                $orden = Orden::query()->whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
                if ($orden->estado !== EstadoOrden::Pagada) {
                    $this->aprobar->ejecutar($bloqueado, $orden, $cargoId);
                }
            } elseif ($tipo === 'charge.failed' || $tipo === 'charge.cancelled') {
                $bloqueado->update(['estado' => EstadoPago::Rechazado->value]);
            }
        });

        return true;
    }
}
