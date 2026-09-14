<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Exceptions\PagoNoEsVentanilla;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\ProveedorPasarela;
use Illuminate\Support\Facades\DB;

/**
 * El staff rechaza un pago de ventanilla (comprobante inválido). No hay
 * fulfillment; la orden queda pendiente para reintentar.
 */
class RechazarVentanilla
{
    public function ejecutar(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago): Pago {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueado->proveedor !== ProveedorPasarela::Ventanilla->value || $bloqueado->estado !== EstadoPago::Pendiente) {
                throw new PagoNoEsVentanilla('No es un pago de ventanilla pendiente.');
            }

            $bloqueado->update(['estado' => EstadoPago::Rechazado->value]);

            return $bloqueado;
        });
    }
}
