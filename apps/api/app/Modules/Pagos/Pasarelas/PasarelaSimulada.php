<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Pasarelas;

use App\Modules\Pagos\Models\Pago;
use Illuminate\Support\Str;

/**
 * Pasarela en línea simulada: representa el adaptador de una pasarela real
 * (el "one online gateway through adapter" del MVP). Aprueba de forma
 * determinista; la integración real se conecta implementando esta misma interfaz.
 */
class PasarelaSimulada implements PasarelaDePago
{
    public function nombre(): string
    {
        return 'simulada';
    }

    public function cobrar(Pago $pago): ResultadoPago
    {
        return ResultadoPago::aprobado('sim-'.Str::lower(Str::random(16)));
    }
}
