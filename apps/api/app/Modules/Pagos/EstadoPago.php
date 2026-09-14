<?php

declare(strict_types=1);

namespace App\Modules\Pagos;

/**
 * Estado de un intento de pago. `Reembolsado` se habilita en Slice 9b.
 */
enum EstadoPago: string
{
    case Pendiente = 'pendiente';
    case Aprobado = 'aprobado';
    case Rechazado = 'rechazado';
    case Reembolsado = 'reembolsado';
}
