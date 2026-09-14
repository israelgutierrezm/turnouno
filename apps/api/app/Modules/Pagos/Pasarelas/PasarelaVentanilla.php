<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;
use Illuminate\Support\Str;

/**
 * Ventanilla: depósito en efectivo/transferencia que el cliente comprueba con un
 * archivo. El cobro queda `pendiente` hasta que el staff revisa el comprobante y
 * lo aprueba (o rechaza). No cobra contra ninguna API.
 */
class PasarelaVentanilla implements PasarelaDePago
{
    public function nombre(): string
    {
        return 'ventanilla';
    }

    public function cobrar(Pago $pago): ResultadoPago
    {
        return ResultadoPago::pendiente('ventanilla_'.Str::lower(Str::random(16)));
    }
}
