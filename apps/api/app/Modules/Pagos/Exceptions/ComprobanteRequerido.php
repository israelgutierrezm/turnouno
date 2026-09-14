<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Exceptions;

/**
 * No se puede aprobar un pago de ventanilla sin comprobante subido.
 */
class ComprobanteRequerido extends PagoException
{
    public function codigo(): string
    {
        return 'PROOF_REQUIRED';
    }
}
