<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El recurso ya esta ocupado (a su cupo) en el horario solicitado: asignarlo
 * generaria una sobre-reserva (R3).
 */
class RecursoNoDisponible extends TenancyException
{
    public function codigo(): string
    {
        return 'RESOURCE_UNAVAILABLE';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
