<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * Se intento facturar un cargo de renta que no admite factura (no esta pagado).
 * Solo se emite CFDI de un cargo liquidado.
 */
class CargoRentaNoFacturable extends TenancyException
{
    public function codigo(): string
    {
        return 'RENT_CHARGE_NOT_INVOICEABLE';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
