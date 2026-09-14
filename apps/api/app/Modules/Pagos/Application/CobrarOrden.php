<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Exceptions\OrdenNoPagable;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Pasarelas\PasarelaDePago;
use Illuminate\Support\Facades\DB;

/**
 * Cobra una orden a través de una pasarela. Si el cobro se aprueba, marca la
 * orden pagada y hace el **fulfillment** de forma atómica: concede un derecho por
 * cada unidad de cada línea al beneficiario (o al comprador si no hay
 * beneficiario). Comprador != participante.
 *
 * Idempotente: por `idempotency_key`, y si la orden ya está pagada devuelve su
 * pago aprobado sin volver a cobrar ni conceder derechos. El cobro se serializa
 * con `lockForUpdate` sobre la orden.
 */
class CobrarOrden
{
    public function __construct(private readonly AprobarPago $aprobar) {}

    public function ejecutar(Orden $orden, PasarelaDePago $pasarela, ?string $idempotencyKey = null): Pago
    {
        if ($idempotencyKey !== null) {
            $previo = Pago::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($previo !== null) {
                return $previo;
            }
        }

        return DB::transaction(function () use ($orden, $pasarela, $idempotencyKey): Pago {
            $bloqueada = Orden::query()->whereKey($orden->getKey())->lockForUpdate()->firstOrFail();

            if ($bloqueada->estado === EstadoOrden::Pagada) {
                return Pago::query()
                    ->where('orden_id', $bloqueada->id)
                    ->where('estado', EstadoPago::Aprobado->value)
                    ->firstOrFail();
            }

            if ($bloqueada->estado === EstadoOrden::Cancelada) {
                throw new OrdenNoPagable('La orden no admite cobro.');
            }

            $pago = Pago::create([
                'orden_id' => $bloqueada->id,
                'proveedor' => $pasarela->nombre(),
                'estado' => EstadoPago::Pendiente->value,
                'monto_minor' => $bloqueada->total_minor,
                'moneda' => $bloqueada->moneda,
                'idempotency_key' => $idempotencyKey,
            ]);

            $resultado = $pasarela->cobrar($pago);

            if (! $resultado->aprobado) {
                $pago->update([
                    'estado' => EstadoPago::Rechazado->value,
                    'referencia_externa' => $resultado->referencia,
                ]);

                return $pago;
            }

            $this->aprobar->ejecutar($pago, $bloqueada, (string) $resultado->referencia);

            return $pago;
        });
    }
}
