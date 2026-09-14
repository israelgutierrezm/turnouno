<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Creditos\EstadoRetencion;
use App\Modules\Creditos\LibroMayor;
use App\Modules\Creditos\TipoMovimiento;
use App\Modules\Membresias\EstadoAcuerdo;
use App\Modules\Membresias\Models\Acuerdo;
use App\Modules\Membresias\Models\Derecho;
use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Exceptions\DerechoYaUsado;
use App\Modules\Pagos\Exceptions\PagoNoReembolsable;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Support\Facades\DB;

/**
 * Reembolsa un pago aprobado y revierte su fulfillment. Política MVP
 * (ver ADR-0012): solo se reembolsa si TODOS los derechos concedidos por la orden
 * están intactos (sin consumo ni retenciones activas); si alguno ya se usó, se
 * bloquea. Al reembolsar: revierte el ledger a 0, cancela los acuerdos, marca el
 * pago reembolsado y la orden cancelada. Idempotente.
 */
class ReembolsarPago
{
    public function __construct(private readonly LibroMayor $libro) {}

    public function ejecutar(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago): Pago {
            $bloqueado = Pago::query()->whereKey($pago->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueado->estado === EstadoPago::Reembolsado) {
                return $bloqueado;
            }

            if ($bloqueado->estado !== EstadoPago::Aprobado) {
                throw new PagoNoReembolsable('Solo se puede reembolsar un pago aprobado.');
            }

            $orden = $bloqueado->orden;
            $orden->loadMissing('lineas');
            $lineaIds = $orden->lineas->pluck('id')->all();

            $acuerdos = Acuerdo::query()
                ->whereIn('linea_orden_id', $lineaIds)
                ->where('estado', '!=', EstadoAcuerdo::Cancelado->value)
                ->with('derechos')
                ->get();

            // Política "bloquear si hubo uso": ningún derecho puede tener consumo ni holds activos.
            foreach ($acuerdos as $acuerdo) {
                foreach ($acuerdo->derechos as $derecho) {
                    if ($this->tuvoUso($derecho)) {
                        throw new DerechoYaUsado('El derecho ya tuvo uso; no se puede reembolsar.');
                    }
                }
            }

            foreach ($acuerdos as $acuerdo) {
                foreach ($acuerdo->derechos as $derecho) {
                    $saldo = $this->libro->saldo($derecho);
                    if ($saldo !== 0) {
                        $this->libro->registrar($derecho, TipoMovimiento::Reverso, -$saldo, 'Reembolso de orden');
                    }
                }
                $acuerdo->update(['estado' => EstadoAcuerdo::Cancelado->value]);
            }

            $bloqueado->update(['estado' => EstadoPago::Reembolsado->value]);
            $orden->update(['estado' => EstadoOrden::Cancelada->value]);

            return $bloqueado;
        });
    }

    private function tuvoUso(Derecho $derecho): bool
    {
        $consumos = $derecho->movimientos()
            ->where('tipo', TipoMovimiento::Consumo->value)
            ->exists();

        $holdsActivos = $derecho->retenciones()
            ->where('estado', EstadoRetencion::Activa->value)
            ->exists();

        return $consumos || $holdsActivos;
    }
}
