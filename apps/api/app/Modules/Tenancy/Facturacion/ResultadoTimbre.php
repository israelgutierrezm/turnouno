<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Facturacion;

/**
 * Resultado de timbrar un CFDI: el id de la factura en FacturAPI y su folio fiscal
 * (UUID del SAT).
 */
final readonly class ResultadoTimbre
{
    public function __construct(
        public string $facturaId,
        public string $uuid,
    ) {}
}
