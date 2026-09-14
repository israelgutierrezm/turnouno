<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Exceptions;

/**
 * La acción de ventanilla requiere un pago de ventanilla pendiente.
 */
class PagoNoEsVentanilla extends PagoException
{
    public function codigo(): string
    {
        return 'NOT_A_COUNTER_PAYMENT';
    }
}
