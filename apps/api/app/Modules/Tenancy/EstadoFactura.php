<?php

declare(strict_types=1);

namespace App\Modules\Tenancy;

/**
 * Estado de una factura (CFDI) tenant-local. Se timbra de forma síncrona vía
 * FacturAPI: queda `Timbrada` (con UUID) o `Error` (con el motivo).
 */
enum EstadoFactura: string
{
    case Timbrada = 'timbrada';
    case Error = 'error';
}
