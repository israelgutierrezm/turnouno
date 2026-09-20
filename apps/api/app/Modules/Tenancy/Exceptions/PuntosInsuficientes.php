<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El miembro no tiene puntos suficientes para canjear la recompensa.
 */
class PuntosInsuficientes extends TenancyException
{
    public function codigo(): string
    {
        return 'POINTS_INSUFFICIENT';
    }

    public function estadoHttp(): int
    {
        return 422;
    }
}
