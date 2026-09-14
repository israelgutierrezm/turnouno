<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;

/**
 * Pago manual (efectivo/transferencia): el staff registra que el dinero se
 * recibió, así que se aprueba de inmediato. Es la pasarela mínima del MVP.
 */
class PasarelaManual implements PasarelaDePago
{
    public function nombre(): string
    {
        return 'manual';
    }

    public function cobrar(Pago $pago): ResultadoPago
    {
        return ResultadoPago::aprobado('manual-'.$pago->ulid);
    }
}
