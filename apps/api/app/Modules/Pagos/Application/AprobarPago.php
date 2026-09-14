<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Application;

use App\Modules\Membresias\Application\CrearAcuerdo;
use App\Modules\Ordenes\EstadoOrden;
use App\Modules\Ordenes\Models\Orden;
use App\Modules\Pagos\EstadoPago;
use App\Modules\Pagos\Models\Pago;

/**
 * Aprueba un pago y hace el fulfillment de su orden: marca ambos y concede un
 * derecho por cada unidad de cada línea al beneficiario (o comprador). Cada
 * derecho queda ligado a su línea (`linea_orden_id`) para poder revertirlo en un
 * reembolso. Se comparte entre el cobro directo y el webhook.
 *
 * Debe ejecutarse dentro de una transacción con la orden ya bloqueada.
 */
class AprobarPago
{
    public function __construct(private readonly CrearAcuerdo $crearAcuerdo) {}

    public function ejecutar(Pago $pago, Orden $orden, string $referencia): void
    {
        $pago->update([
            'estado' => EstadoPago::Aprobado->value,
            'referencia_externa' => $referencia,
        ]);
        $orden->update(['estado' => EstadoOrden::Pagada->value]);

        $orden->loadMissing(['lineas.producto', 'lineas.beneficiario', 'persona']);

        foreach ($orden->lineas as $linea) {
            $beneficiario = $linea->beneficiario ?? $orden->persona;

            for ($i = 0; $i < $linea->cantidad; $i++) {
                $acuerdo = $this->crearAcuerdo->ejecutar($beneficiario, $linea->producto);
                $acuerdo->update(['linea_orden_id' => $linea->id]);
            }
        }
    }
}
