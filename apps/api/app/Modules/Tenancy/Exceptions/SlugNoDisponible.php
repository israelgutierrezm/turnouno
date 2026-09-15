<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Exceptions;

/**
 * El slug del estudio ya está tomado (incluye la carrera entre registros
 * concurrentes que superan la validación pero chocan con el índice único).
 */
class SlugNoDisponible extends TenancyException
{
    public function codigo(): string
    {
        return 'SLUG_TAKEN';
    }

    public function estadoHttp(): int
    {
        return 409;
    }
}
