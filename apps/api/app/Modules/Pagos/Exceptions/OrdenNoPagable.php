<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Exceptions;

/**
 * La orden no está en un estado que admita cobro (p. ej. cancelada).
 */
class OrdenNoPagable extends PagoException
{
    public function codigo(): string
    {
        return 'ORDER_NOT_PAYABLE';
    }
}
