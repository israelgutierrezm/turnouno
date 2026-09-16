<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El estudio intento cobrar con una pasarela que no esta activa/configurada.
 */
class PasarelaNoDisponible extends TenancyException
{
    public function codigo(): string
    {
        return 'GATEWAY_UNAVAILABLE';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
