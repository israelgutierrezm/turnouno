<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * Se intento pagar un cargo de renta que no admite cobro (por ejemplo, ya esta
 * pagado). El cargo debe estar `pendiente` para poder cobrarse.
 */
class CargoRentaNoPagable extends TenancyException
{
    public function codigo(): string
    {
        return 'RENT_CHARGE_NOT_PAYABLE';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
