<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Exceptions\ComprobanteRequerido;
use App\Modules\Pagos\Exceptions\PagoNoEsVentanilla;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\ProveedorPasarela;
use Illuminate\Support\Facades\DB;

/**
 * El staff aprueba un pago de ventanilla tras revisar el comprobante: hace el
 * fulfillment (concede los derechos). Requiere un pago de ventanilla pendiente y
 * con comprobante. Idempotente si la orden ya está pagada.
 */
class AprobarVentanilla
{
    public function __construct(private readonly AprobarPago $aprobar) {}

    public function ejecutar(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago): Pago {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueado->proveedor !== ProveedorPasarela::Ventanilla->value || $bloqueado->estado !== EstadoPago::Pendiente) {
                throw new PagoNoEsVentanilla('No es un pago de ventanilla pendiente.');
            }

            if ($bloqueado->comprobante_ruta === null) {
                throw new ComprobanteRequerido('Falta el comprobante de depósito.');
            }

            $orden = Orden::query()->whereKey($bloqueado->orden_id)->lockForUpdate()->firstOrFail();
            if ($orden->estado === EstadoOrden::Pagada) {
                return $bloqueado;
            }

            $this->aprobar->ejecutar($bloqueado, $orden, (string) ($bloqueado->referencia_externa ?? 'ventanilla'));

            return $bloqueado;
        });
    }
}
