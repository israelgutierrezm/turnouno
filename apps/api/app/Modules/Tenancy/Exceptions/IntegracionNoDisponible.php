<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * Se intento un check-in con una plataforma de bienestar no activa/configurada en
 * el estudio.
 */
class IntegracionNoDisponible extends TenancyException
{
    public function codigo(): string
    {
        return 'INTEGRATION_UNAVAILABLE';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
